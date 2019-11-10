-- all of these procedures / functions used a zero in a date, which isn't allowed in strict mode.  they'll be re-created after this
drop function if exists GetLastFullMonth;
drop procedure if exists GetMonthlyCategorySpending;
drop procedure if exists GetTransactions;
drop procedure if exists GetYearlyCategorySpending;
drop view if exists spending_monthly;

-- these tables stored a month as a date with a zero day, so update them to use day 1.  the comment on the columns will still say 00.
update budget_categories set month=date_format(month, '%Y-%m-01');
update budget_funds set month=date_format(month, '%Y-%m-01');
