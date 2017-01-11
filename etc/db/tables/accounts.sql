create table accounts (
  id tinyint unsigned primary key auto_increment,
  bank smallint unsigned,
  foreign key(bank) references banks(id) on update cascade on delete cascade,
  name varchar(32),
  balance decimal(10,2) not null default 0,
  closed bool not null default 0
);
