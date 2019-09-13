import BudgetApi from "../api/budget.js";
import FundApi from "../api/fund.js";
import ReportErrors from "../reportErrors.js";
import FilterAmountKeys from "../filterAmountKeys.js";

export default {
	props: [
		"month"
	],
	data() {
		return {
			names: [],
			categories: [],
			funds: []
		};
	},
	computed: {
		incomes() {
			return this.categories.filter(cat => cat.amount > 0 || !cat.amount && cat.amounts.reduce((s, c) => s + c, 0) > 0);
		},
		expenses() {
			return this.categories.filter(cat => cat.amount < 0 || !cat.amount && cat.amounts.reduce((s, c) => s + c, 0) < 0);
		},
		total() {
			return +(this.categories.reduce((s, c) => s + +c.amount, 0) - this.funds.reduce((s, f) => s + +f.amount, 0)).toFixed(2);
		}
	},
	watch: {
		month(month) {
			if(month)
				this.Load();
		}
	},
	created() {
		if(this.month)
			this.Load();
		FundApi.List().done(funds => {
			this.funds = funds.filter(f => f.target != 0 || f.balance != 0).map(f => Object.assign(f, { amount: "" }));
		}).fail(this.Error);
	},
	methods: {
		Load() {
			BudgetApi.Suggestions(this.month.sort).done(result => {
				this.names = result.columns;
				this.categories = result.values.map(c => Object.assign(c, { amount: "" }));
			}).fail(this.Error);
		},
		Remove(item) {
			this.categories.splice(this.categories.indexOf(item), 1);
		},
		Create() {
			const categories = FlattenCategories(this.categories);
			const funds = FlattenFunds(this.funds);
			BudgetApi.Create(this.month.sort, categories.ids, categories.amounts, funds.ids, funds.amounts).done(() => {
				location.reload();
			}).fail(this.Error);
		}
	},
	mixins: [
		ReportErrors,
		FilterAmountKeys
	],
	template: /*html*/ `
		<table class="budget new">
			<tbody class=income>
				<tr><th class=heading :colspan="names.length + 2">Expected Income</th></tr>
				<template v-for="(income, index) in incomes">
					<tr v-if="income.groupname && (!index || incomes[index - 1].groupname != income.groupname)">
						<th>{{income.groupname}}</th>
						<th></th>
						<th class=amount v-for="name in names">{{name}}</th>
					</tr>
					<tr>
						<td>
							<span>{{income.catname}}</span>
							<a class=remove href=#budget!remove :title="'Exclude ' + income.catname + ' from this budget'" @click.prevent=Remove(income)><span>X</span></a>
						</td>
						<td class=amount><input v-model.number.lazy=income.amount type=number step=.01 @keypress=FilterAmountKeys></td>
						<td class=amount v-for="(n, i) in names" @click="income.amount = income.amounts[i]">{{income.amounts[i] ? income.amounts[i].toFixed(2) : ""}}</td>
					</tr>
				</template>
			</tbody>
			<tbody class=expenses>
				<tr><th class=heading :colspan="names.length + 2">Budget Items</th></tr>
				<template v-for="(expense, index) in expenses">
					<tr v-if="expense.groupname && (!index || expenses[index - 1].groupname != expense.groupname)">
						<th>{{expense.groupname}}</th>
						<th></th>
						<th class=amount v-for="name in names">{{name}}</th>
					</tr>
					<tr>
						<td>
							<span>{{expense.catname}}</span>
							<a class=remove href=#budget!remove :title="'Exclude ' + expense.catname + ' from this budget'" @click.prevent=Remove(expense)><span>X</span></a>
						</td>
						<td class=amount><input :value="expense.amount ? -expense.amount : ''" @change="expense.amount = $event.target.value ? -$event.target.value : ''" type=number step=.01 @keybind=FilterAmountKeys></td>
						<td class=amount v-for="(n, i) in names" @click="expense.amount = expense.amounts[i]">{{expense.amounts[i] ? (-expense.amounts[i]).toFixed(2) : ""}}</td>
					</tr>
				</template>
			</tbody>
			<tbody class=saving>
				<tr><th class=heading :colspan="names.length + 2">Saving</th></tr>
				<tr v-for="fund in funds">
					<td>{{fund.name}}</td>
					<td class=amount><input v-model.number=fund.amount type=number step=.01 @keypress=FilterAmountKeys></td>
					<td class=amount :colspan=names.length>{{fund.balanceDisplay}} of {{fund.targetDisplay}} ({{fund.target ? Math.round(Math.max(0, Math.min(100, 100 * fund.balance / fund.target))) : (fund.balance ? 100 : 0)}}%)</td>
				</tr>
			</tbody>
			<tfoot><tr>
				<th>Total:  {{total.toFixed(2)}}</th>
				<th><div><button :title="'Create this budget for ' + month.display" @click=Create>Create</button></div></th>
			</tr></tfoot>
		</table>
	`
};

function FlattenCategories(categories) {
	const cats = { ids: [], amounts: [] };
	for(const cat of categories)
		if(cat.amount) {
			cats.ids.push(cat.catid);
			cats.amounts.push(-cat.amount);
		}
	return cats;
}

function FlattenFunds(funds) {
	const f = { ids: [], amounts: [] };
	for(const fund of funds)
		if(fund.amount) {
			f.ids.push(fund.id);
			f.amounts.push(fund.amount);
		}
	return f;
}
