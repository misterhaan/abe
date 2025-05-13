import BudgetApi from "../api/budget.js";
import FilterAmountKeys from "../filterAmountKeys.js";

export default {
	props: [
		"month",
		"months"
	],
	data() {
		return {
			categories: [],
			funds: [],
			expanded: false,
			add: 0
		};
	},
	computed: {
		prevMonth() {
			const index = this.months.indexOf(this.month);
			return index
				? this.months[index - 1]
				: null;
		},
		nextMonth() {
			const index = this.months.indexOf(this.month) + 1;
			return index < this.months.length
				? this.months[index]
				: null;
		},
		nextNewMonth() {
			let year;
			let month;
			if(this.months.length) {
				const lastMonth = this.months[this.months.length - 1].sort.split("-");
				year = +lastMonth.shift();
				month = +lastMonth.shift();
			} else {
				const today = new Date();
				year = today.getFullYear();
				month = today.getMonth() + 1;
			}
			month++;
			if(month > 12) {
				month -= 12;
				year++;
			}
			if(month < 10)
				month = "0" + month;
			return { sort: year + "-" + month, display: GetMonthName(+month) + " " + year };
		},
		incomes() {
			return this.categories.filter(cat => cat.catid && (cat.planned < 0 || cat.planned == 0 && cat.amount < 0));
		},
		expenses() {
			return this.categories.filter(cat => cat.catid && (cat.planned > 0 || cat.planned == 0 && cat.amount > 0));
		},
		uncategorized() {
			const uncat = this.categories.find(cat => !cat.catid);
			return uncat ? uncat.amount : 0;
		},
		total() {
			return +(
				this.categories.reduce((sum, cat) => sum += cat.actual, 0)
				+ this.uncategorized
				+ this.funds.reduce((sum, f) => sum += f.actual, 0)
			).toFixed(2);
		},
		hasDifferentSpending() {
			return this.categories.some(cat => cat.catid && cat.actual != cat.amount);
		}
	},
	created() {
		if(this.month)
			this.Load();
	},
	watch: {
		month(month) {
			if(month)
				this.Load();
		}
	},
	methods: {
		Load() {
			BudgetApi.LoadActive(this.month.sort).done(results => {
				this.categories = results.categories;
				this.funds = results.funds;
			});
		},
		Expand(item) {
			if(item != this.expanded) {
				this.Save();
				this.expanded = item;
				this.add = "";
				setTimeout(() => $("input.amount").focus());
			}
		},
		ExpandFund(fund) {
			this.Expand(fund);
			this.add = fund.actual;
		},
		Save() {
			if(this.expanded) {
				const item = this.expanded;
				if(this.funds.includes(item)) {
					const amount = this.add;
					BudgetApi.SetActualFund(this.month.sort, item.id, amount, item.actual).done(() => {
						item.actual = amount;
					});
				} else if(this.add) {
					const amount = item.planned < 0
						? item.actual - this.add
						: item.actual + this.add;
					BudgetApi.SetActual(this.month.sort, item.catid, amount).done(() => {
						item.actual = amount;
					});
				}
			}
			this.add = 0;
			this.expanded = false;
		},
		SetSpent(item) {
			BudgetApi.SetActual(this.month.sort, item.catid, item.amount).done(() => {
				item.actual = item.amount;
			});
		},
		SetAllSpent() {
			const ids = [];
			const amounts = [];
			for(const cat of this.categories)
				if(cat.catid && cat.amount != cat.actual) {
					ids.push(cat.catid);
					amounts.push(cat.amount);
				}
			BudgetApi.SetActual(this.month.sort, ids, amounts).done(() => {
				let id = ids.shift();
				let amount = amounts.shift();
				for(const cat of this.categories)
					if(cat.catid == id) {
						cat.actual = amount;
						id = ids.shift();
						amount = amounts.shift();
					}
			});
		},
		LastDayOfMonth(yyyymm) {
			const ym = yyyymm.split("-");
			const lastDay = new Date(ym.shift(), +ym.shift(), 0);
			return lastDay.getFullYear() + "-" + ("0" + (lastDay.getMonth() + 1)).slice(-2) + "-" + ("0" + (lastDay.getDate())).slice(-2);
		}
	},
	mixins: [FilterAmountKeys],
	template: /*html*/ `
		<div id=activebudget>
			<nav>
				<span><a class=prev title="" :href="'#budget!month=' + prevMonth.sort" v-if=prevMonth>{{prevMonth.display}}</a></span>
				<span><a class=auto href=#budget!setAllSpent v-if=hasDifferentSpending @click.prevent=SetAllSpent>Update</a></span>
				<span>
					<a class=next :href="'#budget!month=' + nextMonth.sort" v-if=nextMonth>{{nextMonth.display}}</a>
					<a class=add :href="'#budget!month=' + nextNewMonth.sort" v-if=!nextMonth>{{nextNewMonth.display}}</a>
				</span>
			</nav>
			<p class=uncat v-if=uncategorized>
				There are {{Math.abs(uncategorized).toFixed(2)}} in
				<a :href="'#transactions!cats=0/datestart=' + month.sort + '-01/dateend=' + LastDayOfMonth(month.sort)">uncategorized transactions for {{month.display}}</a>.
			</p>
			<template v-if=incomes.length>
				<h2 class=budgetsect>Expected Income</h2>
				<template v-for="(income, index) in incomes">
					<header class=budgetgroup v-if="income.groupname && (!index || incomes[index - 1].groupname != income.groupname)">{{income.groupname}}</header>
					<div class="budgetitem income" @click=Expand(income)>
						<div class=info>
							<div>{{income.catname}}</div>
							<div class=values>
								{{(-income.actual).toFixed(2)}}
								<span class=spent title="Transactions show a different amount for this category" v-if="income.actual != income.amount">
									({{(-income.amount).toFixed(2)}})
									<a class=auto title="Set budget item to amount from categorized transactions" href=#budget!setSpent @click.stop.prevent=SetSpent(income)><span>*</span></a>
								</span>
								of {{(-income.planned).toFixed(2)}}
							</div>
						</div>
						<div class=percentfield>
							<div class=percentvalue :style="{width: (income.planned ? Math.max(0, Math.min(100, 100 * income.actual / income.planned)) : income.actual ? 100 : 0) + '%'}"></div>
						</div>
						<div class=expanded v-if="income == expanded">
							Plus
							<input type=number class=amount step=.01 v-model.number=add @keypress=FilterAmountKeys @keydown.enter=Save>
							= {{((add || 0) - income.actual).toFixed(2)}}
							<button class=save title="Save current budget value" @click.stop=Save></button>
						</div>
					</div>
				</template>
			</template>
			<template v-if=expenses.length>
				<h2 class=budgetsect>Budget Items</h2>
				<template v-for="(expense, index) in expenses">
					<header class=budgetgroup v-if="expense.groupname && (!index || expenses[index - 1].groupname != expense.groupname)">{{expense.groupname}}</header>
					<div class="budgetitem expense" @click=Expand(expense)>
						<div class=info>
							<div>{{expense.catname}}</div>
							<div class=values>
								{{expense.actual.toFixed(2)}}
								<span class=spent title="Transactions show a different amount for this category" v-if="expense.actual != expense.amount">
									({{(expense.amount).toFixed(2)}})
									<a class=auto title="Set budget item to amount from categorized transactions" href=#budget!setSpent @click.stop.prevent=SetSpent(expense)><span>*</span></a>
								</span>
								of {{expense.planned.toFixed(2)}}
							</div>
						</div>
						<div class=percentfield>
							<div class=percentvalue :style="{width: (expense.planned ? Math.max(0, Math.min(100, 100 * expense.actual / expense.planned)) : expense.actual ? 100 : 0) + '%'}"></div>
						</div>
						<div class=expanded v-if="expense == expanded">
							Plus
							<input type=number class=amount step=.01 v-model.number=add @keypress=FilterAmountKeys @keydown.enter=Save>
							= {{((add || 0) + expense.actual).toFixed(2)}}
							<button class=save title="Save current budget value" @click.stop=Save></button>
						</div>
					</div>
				</template>
			</template>
			<template v-if=funds.length>
				<header class=saving>
					<h2 class=budgetsect>Saving</h2>
					<div v-if="total < 0">{{(-total).toFixed(2)}} available for saving</div>
					<div v-if="total > 0">{{total.toFixed(2)}} needs to come out of saving</div>
				</header>
				<div class="budgetitem saving" v-for="fund in funds" @click=ExpandFund(fund)>
					<div class=info>
						<div>{{fund.name}}</div>
						<div class=values>
							<span v-if="fund != expanded">{{(+fund.actual).toFixed(2)}}</span>
							<input v-if="fund == expanded" type=number class=amount step=.01 v-model.number=add @keypress=FilterAmountKeys @keydown.enter=Save>
							of {{fund.planned.toFixed(2)}}
							<button v-if="fund == expanded" class=save title="Save current budget value" @click.stop=Save></button>
						</div>
					</div>
					<div class=percentfield>
						<div class=percentvalue :style="{width: Math.max(0, Math.min(100, 100 * fund.actual / fund.planned)) + '%'}"></div>
					</div>
				</div>
			</template>
		</div>
	`
};

function GetMonthName(month) {
	switch(+month) {
		case 1: return "Jan";
		case 2: return "Feb";
		case 3: return "Mar";
		case 4: return "Apr";
		case 5: return "May";
		case 6: return "Jun";
		case 7: return "Jul";
		case 8: return "Aug";
		case 9: return "Sep";
		case 10: return "Oct";
		case 11: return "Nov";
		case 12: return "Dec";
	}
	return "???";
};
