create table account_types (
  id tinyint unsigned primary key auto_increment,
  name varchar(16),
  unique(name)
);
