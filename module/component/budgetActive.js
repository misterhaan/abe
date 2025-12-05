import { nextTick } from "vue";
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
				const lastMonth = this.months[this.months.length - 1].Sort.split("-");
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
			return { Sort: year + "-" + month, Display: GetMonthName(+month) + " " + year };
		},
		incomes() {
			return this.categories.filter(cat => cat.ID && (cat.Planned < 0 || cat.Planned == 0 && cat.Amount < 0));
		},
		expenses() {
			return this.categories.filter(cat => cat.ID && (cat.Planned > 0 || cat.Planned == 0 && cat.Amount > 0));
		},
		uncategorized() {
			const uncat = this.categories.find(cat => !cat.ID);
			return uncat ? uncat.Amount : 0;
		},
		total() {
			return +(
				this.categories.reduce((sum, cat) => sum += cat.Actual, 0)
				+ this.uncategorized
				+ this.funds.reduce((sum, f) => sum += f.Actual, 0)
			).toFixed(2);
		},
		hasDifferentSpending() {
			return this.categories.some(cat => cat.ID && cat.Actual != cat.Amount);
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
		async Load() {
			const result = await BudgetApi.LoadActive(this.month.Sort);
			this.categories = result.Categories;
			this.funds = result.Funds;
		},
		async Expand(item) {
			if(item != this.expanded) {
				this.Save();
				this.expanded = item;
				this.add = "";
				await nextTick();
				document.querySelector("input.amount").focus();
			}
		},
		ExpandFund(fund) {
			this.Expand(fund);
			this.add = fund.Actual;
		},
		async Save() {
			if(this.expanded) {
				const item = this.expanded;
				if(this.funds.includes(item)) {
					const amount = this.add;
					await BudgetApi.SetActualFund(this.month.Sort, item.ID, amount, item.Actual);
					item.Actual = amount;
				} else if(this.add) {
					const amount = item.Planned < 0
						? item.Actual - this.add
						: item.Actual + this.add;
					await BudgetApi.SetActual(this.month.Sort, item.ID, amount);
					item.Actual = amount;
				}
			}
			this.add = 0;
			this.expanded = false;
		},
		async SetSpent(item) {
			await BudgetApi.SetActual(this.month.Sort, item.ID, item.Amount);
			item.Actual = item.Amount;
		},
		async SetAllSpent() {
			const ids = [];
			const amounts = [];
			for(const cat of this.categories)
				if(cat.ID && cat.Amount != cat.Actual) {
					ids.push(cat.ID);
					amounts.push(cat.Amount);
				}
			await BudgetApi.SetActual(this.month.Sort, ids, amounts);
			let id = ids.shift();
			let amount = amounts.shift();
			for(const cat of this.categories)
				if(cat.ID == id) {
					cat.Actual = amount;
					id = ids.shift();
					amount = amounts.shift();
				}
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
				<span><a class=prev title="" :href="'#budget!month=' + prevMonth.Sort" v-if=prevMonth>{{prevMonth.Display}}</a></span>
				<span><a class=auto href=#budget!setAllSpent v-if=hasDifferentSpending @click.prevent=SetAllSpent>Update</a></span>
				<span>
					<a class=next :href="'#budget!month=' + nextMonth.Sort" v-if=nextMonth>{{nextMonth.Display}}</a>
					<a class=add :href="'#budget!month=' + nextNewMonth.Sort" v-if=!nextMonth>{{nextNewMonth.Display}}</a>
				</span>
			</nav>
			<p class=uncat v-if=uncategorized>
				There are {{Math.abs(uncategorized).toFixed(2)}} in
				<a :href="'#transactions!cats=0/datestart=' + month.Sort + '-01/dateend=' + LastDayOfMonth(month.Sort)">uncategorized transactions for {{month.Display}}</a>.
			</p>
			<template v-if=incomes.length>
				<h2 class=budgetsect>Expected Income</h2>
				<template v-for="(income, index) in incomes">
					<header class=budgetgroup v-if="income.Group && (!index || incomes[index - 1].Group != income.Group)">{{income.Group}}</header>
					<div class="budgetitem income" @click=Expand(income)>
						<div class=info>
							<div>{{income.Name}}</div>
							<div class=values>
								{{(-income.Actual).toFixed(2)}}
								<span class=spent title="Transactions show a different amount for this category" v-if="income.Actual != income.Amount">
									({{(-income.Amount).toFixed(2)}})
									<a class=auto title="Set budget item to amount from categorized transactions" href=#budget!setSpent @click.stop.prevent=SetSpent(income)><span>*</span></a>
								</span>
								of {{(-income.Planned).toFixed(2)}}
							</div>
						</div>
						<div class=percentfield>
							<div class=percentvalue :style="{width: (income.Planned ? Math.max(0, Math.min(100, 100 * income.Actual / income.Planned)) : income.Actual ? 100 : 0) + '%'}"></div>
						</div>
						<div class=expanded v-if="income == expanded">
							Plus
							<input type=number class=amount step=.01 v-model.number=add @keypress=FilterAmountKeys @keydown.enter=Save>
							= {{((add || 0) - income.Actual).toFixed(2)}}
							<button class=save title="Save current budget value" @click.stop=Save></button>
						</div>
					</div>
				</template>
			</template>
			<template v-if=expenses.length>
				<h2 class=budgetsect>Budget Items</h2>
				<template v-for="(expense, index) in expenses">
					<header class=budgetgroup v-if="expense.Group && (!index || expenses[index - 1].Group != expense.Group)">{{expense.Group}}</header>
					<div class="budgetitem expense" @click=Expand(expense)>
						<div class=info>
							<div>{{expense.Name}}</div>
							<div class=values>
								{{expense.Actual.toFixed(2)}}
								<span class=spent title="Transactions show a different amount for this category" v-if="expense.Actual != expense.Amount">
									({{(expense.Amount).toFixed(2)}})
									<a class=auto title="Set budget item to amount from categorized transactions" href=#budget!setSpent @click.stop.prevent=SetSpent(expense)><span>*</span></a>
								</span>
								of {{expense.Planned.toFixed(2)}}
							</div>
						</div>
						<div class=percentfield>
							<div class=percentvalue :style="{width: (expense.Planned ? Math.max(0, Math.min(100, 100 * expense.Actual / expense.Planned)) : expense.Actual ? 100 : 0) + '%'}"></div>
						</div>
						<div class=expanded v-if="expense == expanded">
							Plus
							<input type=number class=amount step=.01 v-model.number=add @keypress=FilterAmountKeys @keydown.enter=Save>
							= {{((add || 0) + expense.Actual).toFixed(2)}}
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
						<div>{{fund.Name}}</div>
						<div class=values>
							<span v-if="fund != expanded">{{(+fund.Actual).toFixed(2)}}</span>
							<input v-if="fund == expanded" type=number class=amount step=.01 v-model.number=add @keypress=FilterAmountKeys @keydown.enter=Save>
							of {{fund.Planned.toFixed(2)}}
							<button v-if="fund == expanded" class=save title="Save current budget value" @click.stop=Save></button>
						</div>
					</div>
					<div class=percentfield>
						<div class=percentvalue :style="{width: Math.max(0, Math.min(100, 100 * fund.Actual / fund.Planned)) + '%'}"></div>
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
