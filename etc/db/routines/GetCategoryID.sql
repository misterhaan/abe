create function GetCategoryID(categoryname varchar(24))
returns tinyint unsigned
begin
  if categoryname='' or categoryname is null then
    return null;
  elseif (select not exists(select id from categories where name=categoryname limit 1)) then
    insert into categories (name) values (categoryname);
  end if;
  return (select id from categories where name=categoryname limit 1);
end
