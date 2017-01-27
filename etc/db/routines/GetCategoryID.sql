create function GetCategoryID(categoryname varchar(16))
returns tinyint unsigned
begin
  if(select not exists(select id from categories where name=categoryname limit 1))
  then
    insert into categories (name) values (categoryname);
  end if;
  return (select id from categories where name=categoryname limit 1);
end
