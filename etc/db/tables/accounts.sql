create table accounts (
  id tinyint unsigned primary key auto_increment,
  bank smallint unsigned,
  foreign key(bank) references banks(id) on update cascade on delete cascade,
  name varchar(32),
  account_type tinyint unsigned,
  foreign key(account_type) references account_types(id) on update cascade on delete cascade,
  updated int not null default 0 comment 'unix timestamp when this account was last updated',
  key(updated),
  balance decimal(10,2) not null default 0,
  closed bool not null default 0
);
