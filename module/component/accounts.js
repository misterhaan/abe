import AccountApi from "../api/account.js";
import ReportErrors from "../reportErrors.js";

export default {
	data() {
		return {
			accounts: [],
			loading: true,
			editing: false,
			types: [],
			banks: [],
			errored: []
		};
	},
	created() {
		Promise.all([
			AccountApi.List().done(accounts => {
				this.accounts = accounts;
			}).fail(this.Error),
			AccountApi.Types().done(result => {
				this.types = result.types;
				this.banks = result.banks;
			}).fail(this.Error)
		]).then(() => {
			this.loading = false;
		});
		this.$emit("add-action", {
			action: this.Add,
			url: "#accounts!add",
			class: "add",
			text: "+",
			tooltip: "Add another account"
		});
	},
	mixins: [ReportErrors],
	methods: {
		Add() {
			this.Save();
			this.editing = {
				name: "",
				type: null,
				typeClass: "",
				bank: null,
				bankName: "",
				bankUrl: "",
				balance: 0,
				balanceDisplay: "",
				newestDisplay: ""
			};
			this.accounts.push(this.editing);
		},
		Edit(account) {
			this.Save();
			this.editing = account;
		},
		Save() {
			if(this.editing) {
				const account = this.editing;
				if(account.name && account.type && account.bank) {
					this.editing = false;
					const index = this.errored.indexOf(account);
					if(index > -1)
						this.errored.slice(index, 1);
					(account.id
						? AccountApi.Save(account.id, account.name, account.type, account.bank, account.balance, account.closed)
						: AccountApi.Add(account.name, account.type, account.bank, account.balance, account.closed)
					).done(id => {
						if(!account.id)
							account.id = id;
						const bank = this.banks.find(b => b.id == account.bank);
						account.bankName = bank.name;
						account.bankUrl = bank.url;
						account.typeClass = this.types.find(t => t.id == account.type).class;
						account.balanceDisplay = account.balance.toFixed(2);
					}).fail(error => {
						this.errored.push(account);
						this.Error(error);
					});
				} else {
					this.errored.push(account);
					this.Error("Accounts must have a name, type, and bank.");
				}
			}
		}
	},
	template: /*html*/ `
		<section id=accountlist>
			<p class=loading v-if=loading>Loading accounts...</p>
			<div class=account v-for="account in accounts" :class="[account.typeClass, {closed: account.closed}]">
				<template v-if="account != editing">
					<h2>{{account.name}}</h2>
					<div class=detail>
						<time class=lastupdate :datetime=account.newestSortable>{{account.newestDisplay ? "As of " + account.newestDisplay : "No transactions so far"}}</time>
						<span class=balance>{{account.balanceDisplay}}</span>
					</div>
					<div class=actions>
						<a class=transactions :href="'#transactions!accts=' + account.id" title="See transactions from this account"><span>transactions</span></a>
						<a class=bank :href=account.bankUrl title="Visit this account’s bank website" v-if=!account.closed><span>bank</span></a>
						<a class=import :href="'#import!acct=' + account.id" title="Import transactions to this account" v-if=!account.closed><span>import</span></a>
						<a class=edit :href="'account.php?id=' + account.id" @click.prevent=Edit(account)><span>edit</span></a>
					</div>
				</template>
				<template v-if="account == editing">
					<h2><input required v-model.trim=account.name placeholder="Account name" maxlength=32></h2>
					<label class=accttype>
						<span class=label>Type:</span>
						<label v-for="type in types">
							<input type=radio name=accttype :value=type.id v-model=account.type>
							<span :class=type.class :title=type.name></span>
						</label>
					</label>
					<label class=bank>
						<span class=label>Bank:</span>
						<select v-model=account.bank>
							<option v-for="bank in banks" :value=bank.id>{{bank.name}}</option>
						</select>
					</label>
					<label class=balance>
						<span class=label>Balance:</span>
						<input type=number step=.01 v-model.number=account.balance>
					</label>
					<label>
						<label class=closed title="Closed accounts are not available for import">
							<input type=checkbox v-model=account.closed>
							<span class=label>Closed</span>
						</label>
						<button @click=Save>Save</button>
					</label>
				</template>
			</div>
		</section>
	`
	// TODO: edit / create accounts on this page
};
