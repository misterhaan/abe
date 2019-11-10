create function GetLastFullMonth()
returns date
begin
	return (select str_to_date(date_format(date_sub(min(posted), interval 1 month), '%Y-%m-01'), '%Y-%m-%d') from
	(select max(t.posted) as posted from transactions as t left join accounts as a on a.id=t.account where a.closed=0 group by t.account) as latestbyaccount);
end
