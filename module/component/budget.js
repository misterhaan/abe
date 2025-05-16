import BudgetApi from "../api/budget.js";
import SuggestBudget from "./budgetSuggestions.js";
import ActiveBudget from "./budgetActive.js";

export default {
	props: [
		"params"
	],
	data() {
		return {
			months: [],
			showGotoForm: false,
			gotoMonth: NextMonth(),
		};
	},
	computed: {
		month() {
			if(this.params.month)
				return this.months.find(m => m.Sort == this.params.month) || { Sort: this.params.month, Display: GetDisplayMonth(this.params.month) };
			if(this.months.length) {
				const now = new Date();
				const currentMonth = now.getFullYear() + "-" + ('0' + (now.getMonth() + 1)).slice(-2);
				for(let m = this.months.length - 1; m >= 0; m--)
					if(this.months[m].Sort <= currentMonth)
						return this.months[m];
				return this.months[this.months.length - 1];
			}
		}
	},
	created() {
		BudgetApi.List().done(months => {
			this.months = months;
			if(!this.months.length)
				this.showGotoForm = true;
		});
		this.$emit("add-action", {
			action: this.ToggleGoto,
			url: "#budget!goto",
			class: "month",
			text: "Go to",
			tooltip: "Create or go to a month’s budget"
		});
	},
	watch: {
		month(month) {
			this.$emit("set-prefix", month ? month.display : "");
		}
	},
	methods: {
		ToggleGoto() {
			this.showGotoForm = !this.showGotoForm;
		},
		Goto() {
			this.showGotoForm = false;
			location = "#budget!month=" + this.gotoMonth;
		}
	},
	components: {
		activeBudget: ActiveBudget,
		suggestBudget: SuggestBudget
	},
	template: /*html*/ `
		<main role=main>
			<div id=newBudget v-if=showGotoForm>
				<label>
					<span>Create or go to budget for:</span>
					<input type=month v-model.trim=gotoMonth>
				</label>
				<div class=calltoaction>
					<button @click.prevent=Goto :disabled=!/[0-9]{4}\-[0-9]{2}/.test(gotoMonth)>{{months.some(m => m.Sort == gotoMonth) ? "Go" : "Create"}}</button>
					<a href="#cancel" @click.prevent=ToggleGoto>Cancel</a>
				</div>
			</div>
			<activeBudget v-if="month && months.includes(month)" :month=month :months=months></activeBudget>
			<suggestBudget v-if="month && !months.includes(month)" :month=month></suggestBudget>
		</main>
	`
};

function NextMonth() {
	var date = new Date();
	var year = date.getFullYear();
	var month = date.getMonth() + 2;  // javascript months start at zero, so add 2 to get to next month
	if(month == 13)
		return (year + 1) + "-01";
	if(month < 10)
		month = "0" + month;
	return year + "-" + month;
}

function GetDisplayMonth(yyyymm) {
	const date = yyyymm.split("-");
	return GetMonthName(date[1]) + " " + date[0];
}

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
