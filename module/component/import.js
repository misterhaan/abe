import AccountApi from "../api/account.js";
import TransactionApi from "../api/transaction.js";
import ReportErrors from "../reportErrors.js";
import "../../external/jquery-3.4.1.min.js";


export default {
	props: [
		'params'
	],
	data() {
		return {
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
		}).fail(this.Error);
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
		Save(preview) {
			preview.working = true;
			TransactionApi.Import(preview.acctid, preview.transactions, preview.net).done(newest => {
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
		},
		Done(preview) {
			this.previews.splice(this.previews.indexOf(preview), 1);
		}
	},
	template: /*html*/ `
		<main role=main>
			<section id=accountlist class=select>
				<div class=account v-for="account in accounts" :class="[account.typeClass, {selected: account == selected}]" @click=Select(account)>
					<h2>{{account.name}}</h2>
					<div class=detail>
						<time class=lastupdate :datetime=account.newestSortable>As of {{account.newestDisplay}}</time>
						<a class=bank :href=account.bankUrl title="Log in to this account’s bank website to download transactions">{{account.bankName}} login</a>
					</div>
				</div>
			</section>
			<label id=transactionsFile :disabled=uploading :class="{working: uploading}">
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
