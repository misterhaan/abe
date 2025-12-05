import { nextTick } from "vue";
import AccountApi from "../api/account.js";

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
	async created() {
		const accountsPromise = AccountApi.List();
		const typesPromise = AccountApi.Types();

		this.$emit("add-action", {
			action: this.Add,
			url: "#accounts!add",
			class: "add",
			text: "+",
			tooltip: "Add another account"
		});

		this.accounts = await accountsPromise;
		if(!this.accounts.length)
			this.Add();
		const types = await typesPromise;
		this.types = types.Types;
		this.banks = types.Banks;
		this.loading = false;
	},
	methods: {
		async Add() {
			this.Save();
			this.editing = {
				Name: "",
				Type: {
					ID: null,
					Class: ""
				},
				Bank: {
					ID: null,
					Name: "",
					URL: ""
				},
				Balance: 0,
				BalanceDisplay: "",
				NewestDisplay: ""
			};
			this.accounts.push(this.editing);
			await nextTick();
			document.querySelector("h2 input").focus();
		},
		async Edit(account) {
			this.Save();
			this.editing = account;
			await nextTick();
			document.querySelector("h2 input").focus();
		},
		async Save() {
			if(this.editing) {
				const account = this.editing;
				if(account.Name && account.Type.ID && account.Bank.ID) {
					this.editing = false;
					const index = this.errored.indexOf(account);
					if(index > -1)
						this.errored.slice(index, 1);
					try {
						const id = await (account.ID
							? AccountApi.Save(account.ID, account.Name, account.Type.ID, account.Bank.ID, account.Balance, account.Closed)
							: AccountApi.Add(account.Name, account.Type.ID, account.Bank.ID, account.Balance, account.Closed)
						);
						if(!account.ID)
							account.ID = id;
						account.Bank = this.banks.find(b => b.ID == account.Bank.ID);
						account.Type = this.types.find(t => t.ID == account.Type.ID);
						account.BalanceDisplay = account.Balance.toFixed(2);
					} catch {
						this.errored.push(account);
					}
				} else {
					this.errored.push(account);
					throw new Error("Accounts must have a name, type, and bank.");
				}
			}
		}
	},
	template: /*html*/ `
		<section id=accountlist>
			<p class=loading v-if=loading>Loading accounts...</p>
			<div class=account v-for="account in accounts" :class="[account.Type.Class, {closed: account.Closed, error: errored.includes(account)}]">
				<template v-if="account != editing">
					<h2>{{account.Name}}</h2>
					<div class=detail>
						<time class=lastupdate :datetime=account.NewestSortable>{{account.NewestDisplay ? "As of " + account.NewestDisplay : "No transactions so far"}}</time>
						<span class=balance>{{account.BalanceDisplay}}</span>
					</div>
					<div class=actions>
						<a class=transactions :href="'#transactions!accts=' + account.ID" title="See transactions from this account"><span>transactions</span></a>
						<a class=bank :href=account.Bank.URL title="Visit this account’s bank website" v-if=!account.Closed><span>bank</span></a>
						<a class=import :href="'#import!acct=' + account.ID" title="Import transactions to this account" v-if=!account.Closed><span>import</span></a>
						<a class=edit :href="'account.php?id=' + account.ID" @click.prevent=Edit(account)><span>edit</span></a>
					</div>
				</template>
				<template v-if="account == editing">
					<h2><input required v-model.trim=account.Name placeholder="Account name" maxlength=32></h2>
					<label class=accttype>
						<span class=label>Type:</span>
						<label v-for="type in types">
							<input type=radio name=accttype :value=type.ID v-model=account.Type.ID>
							<span :class=type.Class :title=type.Name></span>
						</label>
					</label>
					<label class=bank>
						<span class=label>Bank:</span>
						<select v-model=account.Bank.ID>
							<option v-for="bank in banks" :value=bank.ID>{{bank.Name}}</option>
						</select>
					</label>
					<label class=balance>
						<span class=label>Balance:</span>
						<input type=number step=.01 v-model.number=account.Balance>
					</label>
					<label>
						<label class=closed title="Closed accounts are not available for import">
							<input type=checkbox v-model=account.Closed>
							<span class=label>Closed</span>
						</label>
						<button @click=Save>Save</button>
					</label>
				</template>
			</div>
		</section>
	`
};
