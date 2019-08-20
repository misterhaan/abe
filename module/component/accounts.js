import AccountApi from "../api/account.js";
import ReportErrors from "../reportErrors.js";

export default {
	data() {
		return {
			accounts: [],
			loading: true
		};
	},
	created() {
		AccountApi.List().done(accounts => {
			this.accounts = accounts;
		}).fail(this.Error).always(() => {
			this.loading = false;
		});
		this.$emit("add-action", {
			action: this.Add,
			url: "account.php",
			class: "add",
			text: "+",
			tooltip: "Add another account"
		});
	},
	mixins: [ReportErrors],
	methods: {
		Add() {
			window.location = "account.php";
		}
	},
	template: /*html*/ `
		<section id=accountlist>
			<p class=loading v-if=loading>Loading accounts...</p>
			<div class=account v-for="account in accounts" :class="[account.typeClass, {closed: account.closed}]">
				<h2>{{account.name}}</h2>
				<div class=detail>
					<time class=lastupdate :datetime=account.newestSortable>As of {{account.newestDisplay}}</time>
					<span class=balance>{{account.balanceDisplay}}</span>
				</div>
				<div class=actions>
					<a class=transactions :href="'transactions.php#!accts=' + account.id" title="See transactions from this account"><span>transactions</span></a>
					<a class=bank :href=account.bankUrl title="Visit this account’s bank website" v-if=!account.closed><span>bank</span></a>
					<a class=import :href="'#import!acct=' + account.id" title="Import transactions to this account" v-if=!account.closed><span>import</span></a>
					<a class=edit :href="'account.php?id=' + account.id"><span>edit</span></a>
				</div>
			</div>
		</section>
	`
	// TODO: change action urls as pages get converted
	// TODO: edit / create accounts on this page
};
