create table categories (
	id tinyint unsigned primary key auto_increment,
	name varchar(24),
	unique(name),
	grp tinyint unsigned,
	foreign key(grp) references category_groups(id)
);
