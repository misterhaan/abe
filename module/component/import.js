import AccountApi from "../api/account.js";
import TransactionApi from "../api/transaction.js";
import ReportErrors from "../reportErrors.js";

export default {
	props: [
		'params'
	],
	data() {
		return {
			loading: true,
			accounts: [],
			selected: false,
			previews: [],
			uploading: false
		};
	},
	created() {
		AccountApi.List(true).done(accounts => {
			this.accounts = accounts;
			this.SortAccounts();
			if(this.params.acct)
				this.selected = this.accounts.find(a => {
					return a.id == this.params.acct;
				});
			if(!this.selected && this.accounts.length)
				this.selected = this.accounts[0];
		}).fail(this.Error).always(() => {
			this.loading = false;
		});
	},
	mixins: [ReportErrors],
	methods: {
		SortAccounts() {
			this.accounts.sort((a, b) => {
				return a.newestSortable.localeCompare(b.newestSortable);
			});
		},
		Select(account) {
			this.selected = account;
		},
		Preview() {
			this.uploading = true;
			const account = this.selected;
			const fileInput = $("input[type='file']");
			TransactionApi.ParseFile(account.id, fileInput[0]).done(preview => {
				preview.acctname = account.name;
				preview.acctid = account.id;
				preview.saved = false;
				preview.working = false;
				this.previews.unshift(preview);
				setTimeout(() => {
					$(window).scrollTop($(".transactions.preview").first().offset().top);
				});
			}).fail(this.Error).always(() => {
				this.uploading = false;
			});
			fileInput.val("");
		},
		Ignore(preview, transaction) {
			preview.transactions.splice(preview.transactions.indexOf(transaction), 1);
		},
		Save(preview, next = 0, oldNewest = false) {
			preview.working = true;
			const last = next + TransactionApi.MaxTransactions;
			const transactions = preview.transactions.slice(next, last);
			const net = next ? 0 : preview.net;  // only adjust net the first time
			const promise = TransactionApi.Import(preview.acctid, transactions, net).then(newNewest => {
				const newest = oldNewest && oldNewest.sortable > newNewest.sortable ? oldNewest : newNewest;
				return last >= preview.transactions.length
					? newest
					: this.Save(preview, last, newest);
			});
			if(!next)
				promise = promise.done(newest => {
					preview.saved = true;
					let account;
					if(preview.acctid == this.selected.id)
						account = this.selected;
					else
						for(let a in this.accounts)
							if(this.accounts[a].id == preview.acctid) {
								account = this.accounts[a];
								break;
							}
					if(account && account.newestSortable < newest.sortable) {
						account.newestSortable = newest.sortable;
						account.newestDisplay = newest.display;
						this.SortAccounts();
					}
				}).fail(this.Error).always(() => {
					preview.working = false;
				});
			return promise;
		},
		Done(preview) {
			this.previews.splice(this.previews.indexOf(preview), 1);
		}
	},
	template: /*html*/ `
		<main role=main>
			<p v-if="!loading && !accounts.length">
				Cannot import because there are no open accounts.  You’ll need to
				<a href=#settings/accounts>add or reopen at least one account</a> first.
			</p>
			<section id=accountlist class=select>
				<div class=account v-for="account in accounts" :class="[account.typeClass, {selected: account == selected}]" @click=Select(account)>
					<h2>{{account.name}}</h2>
					<div class=detail>
						<time class=lastupdate :datetime=account.newestSortable>As of {{account.newestDisplay}}</time>
						<a class=bank :href=account.bankUrl title="Log in to this account’s bank website to download transactions">{{account.bankName}} login</a>
					</div>
				</div>
			</section>
			<label id=transactionsFile :disabled=uploading :class="{working: uploading}" v-if=accounts.length>
				Transaction file:
				<input type=file @change=Preview>
			</label>
			<section class="transactions preview" v-for="preview in previews">
				<h2>{{preview.name}} → {{preview.acctname}}</h2>
				<header>
					<span class=count>{{preview.transactions.length}} transactions</span>
					<span class=duplicates>{{Math.round(preview.dupeCount * 100 / preview.transactions.length)}}% duplicates</span>
					<span class=amount>{{preview.net.toFixed(2)}} net</span>
					<span class=status v-if=preview.saved>Imported</span>
					<button v-if=!preview.saved :class="{working: preview.working}" :disabled=preview.working @click=Save(preview)>Save</button>
					<a class=dismiss href="#import!done" title="Remove this preview" @click=Done(preview)>Dismiss</a>
				</header>
				<ul v-for="transaction in preview.transactions">
					<li class=transaction>
						<div class=quick>
							<div class=name>{{transaction.name}}</div>
							<div class=amount :class="{duplicate: transaction.duplicate}" :title="transaction.duplicate ? 'Abe already has this transaction' : null">{{transaction.amount.toFixed(2)}}</div>
							<a class=delete v-if="transaction.duplicate || !transaction.amount" href="#import!ignore" @click="Ignore(preview, transaction)" title="Exclude this transaction from import"><span>ignore</span></a>
						</div>
						<div class=detail>
							<div class="transdate" v-if=transaction.transdate>Transaction <time>{{transaction.transdate}}</time></div>
							<div class="posted">Posted <time>{{transaction.posted}}</time></div>
							<div class="note" v-if=transaction.notes>{{transaction.notes}}</div>
							<div class="location" v-if=transaction.city>{{transaction.city + (transaction.state ? ', ' + transaction.state + (transaction.zip ? ' ' + transaction.zip : '') : '')}}</div>
						</div>
					</li>
				</ul>
			</section>
		</main>
	`
};
