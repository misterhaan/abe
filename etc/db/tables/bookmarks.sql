create table bookmarks (
	id smallint unsigned primary key auto_increment,
	sort tinyint unsigned not null,
	page varchar(16) not null,
	spec varchar(128) not null,
	unique(page, spec),
	name varchar(64) not null
);
