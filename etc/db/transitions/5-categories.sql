-- this runs after the category_groups table is created.
-- add the new column and foreign key
alter table categories add grp tinyint unsigned after parent, add foreign key(grp) references category_groups(id);
-- copy parent categories into category_groups table
insert into category_groups (name) select c.name from categories as c where isnull(c.parent) and exists (select 1 from categories as sc where sc.parent=c.id limit 1);
-- set new categories.grp column value to id in new category_groups table
update categories as c inner join categories as pc on pc.id=c.parent inner join category_groups as g on g.name=pc.name set c.grp=g.id where not isnull(c.parent);
-- remove old foreign key and column
select concat('alter table categories drop foreign key ', (select CONSTRAINT_NAME from INFORMATION_SCHEMA.KEY_COLUMN_USAGE where TABLE_NAME='categories' and CONSTRAINT_SCHEMA=database() and CONSTRAINT_SCHEMA=TABLE_SCHEMA and CONSTRAINT_SCHEMA=REFERENCED_TABLE_SCHEMA and TABLE_NAME=REFERENCED_TABLE_NAME and REFERENCED_COLUMN_NAME='id'), ', drop index parent, drop parent') into @drop_parent_sql;
prepare drop_parent from @drop_parent_sql;
execute drop_parent;
-- delete parent categories that have been copied into category_groups table
delete categories from categories inner join category_groups on categories.name=category_groups.name;
-- need to recreate stored procedures that use the old structure.  they'll be re-imported after this.
drop procedure GetMonthlyCategorySpending;
drop procedure GetYearlyCategorySpending;
