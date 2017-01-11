create table categories (
  id tinyint unsigned primary key auto_increment,
  name varchar(16),
  unique(name),
  parent tinyint unsigned,
  foreign key(parent) references categories(id)
);
