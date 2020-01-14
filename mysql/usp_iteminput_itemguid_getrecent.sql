CREATE DEFINER=`root`@`%` PROCEDURE `usp_iteminput_itemguid_getrecent`(
IN input_source_file_log_id INT(12))
BEGIN
    
    SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
    SET @input_source_file_log_id = input_source_file_log_id;
    SET @row_number:=0;
    SET @item_unique_id:='';
    
	SELECT
		jl.input_source_item_log_id AS 'id',
		jl.item_unique_id AS 'uniqueId',
		jl.item_hash_value AS 'hash',
		DATEDIFF('1970-01-01 00:00:00',jl.created_date) AS 'createdTimestamp',
		jg.item_guid as 'guid'
	FROM gaukmedi_auctions.input_source_item_log jl INNER JOIN 
	gaukmedi_auctions.input_source_file_log fl 
		ON fl.input_source_file_log_id = jl.input_source_file_log_id INNER JOIN
	
    (
		SELECT 
			result2.item_guid,
			result2.rownumber,
			result2.item_unique_id
		FROM
				( SELECT result1.item_guid,
						@row_number:=CASE
							WHEN @itemUniqueId = result1.item_unique_id THEN @row_number + 1
							ELSE 1
						END AS rownumber,
						@itemUniqueId:=result1.item_unique_id as 'item_unique_id'
							FROM (
									SELECT 
											j.item_guid, 
											jl.item_unique_id
											FROM gaukmedi_auctions.input_source_item_log jl
											INNER JOIN
											gaukmedi_auctions.input_source_file_log fl 
												ON jl.input_source_file_log_id = fl.input_source_file_log_id INNER JOIN
												
											(SELECT jl2.item_unique_id, jl2.input_source_file_log_id, fl.input_source_file_id
												FROM gaukmedi_auctions.input_source_item_log jl2 INNER JOIN 
												gaukmedi_auctions.input_source_file_log fl
													ON jl2.input_source_file_log_id = fl.input_source_file_log_id 
												WHERE jl2.input_source_file_log_id = @input_source_file_log_id
											) as jn
											
												ON jl.item_unique_id = jn.item_unique_id 
												AND fl.input_source_file_id = jn.input_source_file_id INNER JOIN
											gaukmedi_auctions.input_item j 
												ON jl.input_source_item_log_id = j.input_source_item_log_id
												 ORDER BY jl.item_unique_id,j.created_date desc
												 LIMIT 18446744073709551615
								) as result1
					) as result2
		WHERE 
				result2.rownumber = 1
	) as jg
		ON jl.item_unique_id = jg.item_unique_id
	WHERE jl.input_source_file_log_id = @input_source_file_log_id
	ORDER BY jl.created_date;
	
    SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ;
END