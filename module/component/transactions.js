import TransactionApi from "../api/transaction.js";
import CategoryGroupApi from "../api/categoryGroup.js";
import FilterAmountKeys from "../filterAmountKeys.js";
import TransactionFilters from "./transactionFilters.js";
import T from "../transactionShared.js";

export default {
	props: [
		'params'
	],
	data() {
		return {
			showFilters: false,
			loading: true,
			catGroups: [],
			catsLoaded: false,
			dates: [],
			oldest: false,
			oldid: false,
			more: false,
			editing: false,
			editCategory: false,
			showSuggestions: false,
			catCursor: false,
			subAmounts: [],
			subAmountMultiplier: false,
			saving: [],
			errored: []
		};
	},
	computed: {
		categoryChoices() {
			if(this.editCategory) {
				const search = this.editCategory.name ? this.editCategory.name.trim().toLowerCase() : "";
				const groups = [];
				for(const group of this.catGroups) {
					let catArr = false;
					if(!search || group.Name.toLowerCase().indexOf(search) > -1) {
						catArr = [];
						groups.push({ name: T.HighlightString(group.Name, search), categories: catArr });
						for(const cat of group.Categories)
							catArr.push({ value: cat.Name, name: T.HighlightString(cat.Name, search) });
					} else
						for(const cat of group.Categories)
							if(!search || cat.Name.toLowerCase().indexOf(search) > -1) {
								if(!catArr) {
									catArr = [];
									groups.push({ name: T.HighlightString(group.Name, ""), categories: catArr });
								}
								catArr.push({ value: cat.Name, name: T.HighlightString(cat.Name, search) });
							}
				}
				return groups;
			}
			return [];
		},
		categoryAmountsTotal() {
			return this.editing
				? this.editing.categories.reduce((s, c) => s + +(c == this.editCategory ? this.subAmountTotal : c.amount), 0)
				: 0;
		},
		subAmountTotal() {
			if(this.editing && this.editCategory) {
				const useMultiplier = this.subAmounts.length && this.subAmountMultiplier;
				const mult = useMultiplier ? +this.editCategory.amount || 1 : 1;
				const start = useMultiplier ? 0 : +this.editCategory.amount;
				return (mult * this.subAmounts.reduce((s, a) => s + a, start)).toFixed(2);
			}
			return (0).toFixed(2);
		}
	},
	created() {
		Promise.all([
			this.LoadCategories(),
			this.LoadTransactions(false)
		]).then(() => {
			this.loading = false;
		});
		this.$emit("add-action", {
			action: this.ToggleFilters,
			url: "#transactions!filters",
			class: "filter",
			text: "Filter",
			tooltip: "Filter transactions"
		});
		this.$emit("add-action", {
			action: this.Import,
			url: "#import",
			class: "import",
			text: "Import",
			tooltip: "Import transactions"
		});
	},
	watch: {
		params() {
			this.dates = [];
			this.oldest = false;
			this.oldid = false;
			this.editing = false;
			this.editCategory = false;
			this.showSuggestions = false;
			this.catCursor = false;
			this.LoadTransactions(true);
		},
		editCategory() {
			this.subAmounts = [];
			this.subAmountMultiplier = false;
		},
		categoryAmountsTotal(total) {
			if(this.editing) {
				const diff = +(this.editing.amount - total).toFixed(2);
				if(diff) {
					for(const c in this.editing.categories) {
						const cat = this.editing.categories[c];
						if(!cat.name) {
							cat.amount = +(+cat.amount + diff).toFixed(2);
							if(!cat.amount)
								this.editing.categories.splice(c, 1);
							return;
						}
					}
					this.editing.categories.push({ name: null, amount: +diff.toFixed(2) });
				}
			}
		}
	},
	mixins: [FilterAmountKeys],
	methods: {
		LoadCategories() {
			return CategoryGroupApi.List().done(groups => {
				this.catGroups = groups;
				this.catsLoaded = true;
			});
		},
		LoadTransactions(setloading = true) {
			if(setloading)
				this.loading = true;
			return TransactionApi.List(this.oldest, this.oldid, this.params.accts, this.params.cats, this.params.datestart, this.params.dateend, this.params.minamount, this.params.search).done(transactions => {
				if(transactions.dates.length && this.dates.length && transactions.dates[0].date == this.dates[this.dates.length - 1].date)
					this.dates[this.dates.length - 1].transactions = this.dates[this.dates.length - 1].transactions.concat(transactions.dates.shift().transactions);
				this.dates = this.dates.concat(transactions.dates);
				if(this.dates.length) {
					let lastdate = this.dates[this.dates.length - 1];
					this.oldest = lastdate.date;
					this.oldid = lastdate.transactions[lastdate.transactions.length - 1].id;
				}
				this.more = transactions.more;
			}).always(() => {
				if(setloading)
					this.loading = false;
			});
		},
		Select(transaction) {
			this.Save();
			this.editCategory = false;
			this.editing = transaction;
			const labelSelector = transaction.categories.length > 1 || transaction.categories[0].name
				? "label.name"
				: "label.category";
			setTimeout(() => {
				$(labelSelector).focus();
			});
		},
		Previous() {
			if(this.editing) {
				const index = this.GetIndices();
				if(index)
					if(index[1])
						this.Select(this.dates[index[0]].transactions[index[1] - 1]);
					else if(index[0]) {
						const transactions = this.dates[index[0] - 1].transactions;
						this.Select(transactions[transactions.length - 1]);
					}
			}
		},
		Next() {
			if(this.editing) {
				const index = this.GetIndices();
				if(index) {
					const transactions = this.dates[index[0]].transactions;
					if(index[1] + 1 < transactions.length)
						this.Select(transactions[index[1] + 1]);
					else if(index[0] + 1 < this.dates.length)
						this.Select(this.dates[index[0] + 1].transactions[0])
				}
			}
		},
		GetIndices() {
			for(const d in this.dates)
				for(const t in this.dates[d].transactions)
					if(this.dates[d].transactions[t] == this.editing)
						return [+d, +t];
			return false;
		},
		CategoryExists(name) {
			if(name)
				name = name.trim().toLowerCase();
			return !name || this.catGroups.some(g => g.Categories.some(c => c.Name.trim().toLowerCase() == name));
		},
		CategoryInput(category, event) {
			category.name = event.target.value;
			this.showSuggestions = true;
		},
		PrevCategory() {
			if(this.categoryChoices.length) {
				this.showSuggestions = true;
				if(this.catCursor) {
					let prev = false;
					for(const group of this.categoryChoices)
						for(const cat of group.categories)
							if(cat.value == this.catCursor) {
								if(prev)
									this.catCursor = prev.value;
								else {
									const lastGroup = this.categoryChoices[this.categoryChoices.length - 1];
									this.catCursor = lastGroup.categories[lastGroup.categories.length - 1].value;
								}
								return;
							}
							else
								prev = cat;
				}
				const lastGroup = this.categoryChoices[this.categoryChoices.length - 1];
				this.catCursor = lastGroup.categories[lastGroup.categories.length - 1].value;
			}
		},
		NextCategory() {
			if(this.categoryChoices.length) {
				this.showSuggestions = true;
				if(this.catCursor) {
					let found = false;
					for(const group of this.categoryChoices)
						for(const cat of group.categories)
							if(cat.value == this.catCursor)
								found = true;
							else if(found) {
								this.catCursor = cat.value;
								return;
							}
				}
				this.catCursor = this.categoryChoices[0].categories[0].value;
			}
		},
		ChooseCategory(category) {
			if(category && this.editCategory) {
				this.editCategory.name = category;
				this.HideSuggestions();
			}
		},
		CategoryBlur(category) {
			if(category.name)
				category.name = category.name.trim();
			this.HideSuggestions();
			if(this.editing) {
				let uncat = false;
				for(let c in this.editing.categories) {
					const cat = this.editing.categories[c];
					if(!cat.name)
						if(!uncat)
							uncat = cat;
						else if(cat == this.editCategory) {
							cat.amount = +cat.amount + +uncat.amount;
							this.editing.categories.splice(this.editing.categories.indexOf(uncat), 1);
							uncat = cat;
							c--;
						} else {
							uncat.amount = +uncat.amount + +cat.amount;
							this.editing.categories.splice(c, 1);
							c--;
						}
				}
			}
		},
		HideSuggestions(event) {
			if(this.showSuggestions) {
				if(event)
					event.stopPropagation();
				this.showSuggestions = false;
				this.catCursor = false;
			}
		},
		PushSubAmount() {
			if(this.editing && this.editCategory && +this.editCategory.amount) {
				this.subAmounts.push(+this.editCategory.amount);
				this.editCategory.amount = "";
			}
		},
		EditSubAmount(index) {
			if(this.editing && this.editCategory && !this.subAmountMultiplier) {
				this.PushSubAmount();
				this.editCategory.amount = +this.subAmounts.splice(index, 1)[0];
				const amountField = $(".subamounts").prev().find("input.catamount");
				amountField.focus();
				amountField.select();
			}
		},
		MultiplySubAmounts() {
			if(!this.subAmountMultiplier) {
				this.PushSubAmount();
				this.subAmountMultiplier = true;
				$(".subamounts").prev().find("input.catamount").focus();
			}
		},
		SumSubAmounts(event) {
			if(this.editing && this.editCategory && this.subAmounts.length && (!event || !event.key || event.key != "+")) {
				this.editCategory.amount = this.subAmountTotal;
				this.subAmounts = [];
				this.subAmountMultiplier = false;
			}
		},
		CloseAndSave(event) {
			if(event)
				event.stopPropagation();
			this.Save();
			this.editCategory = false;
			this.editing = false;
		},
		Save() {
			if(this.editing) {
				const transaction = this.editing;
				let anyEmptyCats = false;
				let catIndex = {};
				let amountDifference = transaction.amount;
				for(let c = 0; c < transaction.categories.length; c++) {
					const cat = transaction.categories[c];
					if(!+cat.amount) {
						transaction.categories.splice(c, 1);
						c--;
					} else {
						amountDifference -= +cat.amount;
						const catName = cat.name ? cat.name.trim().toLowerCase() : cat.name;
						if(!catName)
							anyEmptyCats = transaction.categories.length > 1;
						else if(catIndex[catName] || catIndex[catName] === 0) {
							const first = transaction.categories[catIndex[catName]];
							first.amount = +first.amount + +cat.amount;
							transaction.categories.splice(c, 1);
							c--;
						} else
							catIndex[catName] = c;
					}
				}
				const errorIndex = this.errored.indexOf(transaction);
				if(!+amountDifference.toFixed(2) && !anyEmptyCats) {
					this.saving.push(transaction);
					if(errorIndex > -1)
						this.errored.splice(errorIndex, 1);
					TransactionApi.Save(transaction.id, transaction.name, transaction.notes, transaction.categories).done(() => {
						this.LoadCategories();
					}).fail(() => {
						this.errored.push(transaction);
					}).always(() => {
						this.saving.splice(this.saving.indexOf(transaction), 1);
					});
				} else {
					if(errorIndex == -1)
						this.errored.push(transaction);
					throw new Error("Can’t save transaction with mulitple categories unless amounts add to total and all amounts are categorized.");
				}
			}
		},
		ToggleFilters() {
			this.showFilters = !this.showFilters;
		},
		Import() {
			location = "#import";
		}
	},
	directives: {
		scrollTo: {
			mounted(el) {
				setTimeout(() => {
					const rect = el.getBoundingClientRect();
					const element = $(el);
					if(rect.top < 0)
						$("html, body").animate({ scrollTop: element.offset().top }, 100);
					else {
						const winHeight = $(window).height();
						if(rect.bottom > winHeight)
							if(rect.height + 3 > winHeight)
								$("html, body").animate({ scrollTop: element.offset().top }, 100);
							else
								$("html, body").animate({ scrollTop: element.offset().top - winHeight + rect.height + 3 }, 100);
					}
				});
			}
		}
	},
	components: {
		transactionFilters: TransactionFilters
	},
	template: /*html*/ `
		<div id=transactions class=transactions>
			<transactionFilters :visible=showFilters :params=params :cat-groups=catGroups :cats-loaded=catsLoaded @close=ToggleFilters></transactionFilters>
			<main role=main>
				<p class=info v-if="!loading && !dates.length">
					No transactions found.  Try changing the search criteria or importing
					transactions using an icon in the upper right.
				</p>
				<ol v-if=dates.length>
					<li class=date v-for="date in dates">
						<header><time>{{date.displayDate}}</time></header>
						<ul>
							<li class=transaction v-for="transaction in date.transactions" :class="[transaction.acctclass, {selected: transaction == editing, saveerror: errored.includes(transaction)}]">
								<template v-if="transaction != editing">
									<div class=quick @click="Select(transaction)">
										<div class=name :class="{working: saving.includes(transaction)}">{{transaction.name}}</div>
										<div class=category :class="{multi: transaction.categories.length > 1}">{{transaction.categories.map(c => { return c.name || '${T.UncategorizedName}'; }).join(", ")}}</div>
									</div>
									<div class=amount @click=Select(transaction)>{{transaction.amountDisplay}}</div>
								</template>
								<div class=full v-if="transaction == editing" v-scrollTo @keydown.esc=CloseAndSave @keydown.page-up.prevent=Previous @keydown.page-down.prevent=Next>
									<div class=transaction>
										<div><label class=name :class="{working: saving.includes(transaction)}"><input v-model=transaction.name maxlength=64></label></div>
										<div class=amount>{{transaction.amountDisplay}}</div>
										<a class=close v-if="!saving.includes(transaction)" href=api/transaction/save @click.prevent=CloseAndSave title="Save changes and close"><span>close</span></a>
										<span class=working v-if="saving.includes(transaction)"></span>
									</div>
									<div class=details v-if="transaction == editing">
										<template v-for="cat in transaction.categories">
											<label class=category>
												<input :value=cat.name :class="{newcat: !CategoryExists(cat.name)}" @focus="editCategory = cat" @blur=CategoryBlur(cat)
													@dblclick="showSuggestions = true" @input="CategoryInput(cat, $event)" @keydown.esc=HideSuggestions
													@keydown.enter=ChooseCategory(catCursor) @keydown.tab=ChooseCategory(catCursor) @keydown.page-up=ChooseCategory(catCursor) @keydown.page-down=ChooseCategory(catCursor)
													@keydown.up=PrevCategory @keydown.down=NextCategory placeholder="${T.UncategorizedName}" maxlength=24>
												<ol class=suggestions v-if="showSuggestions && cat == editCategory">
													<template v-for="group in categoryChoices">
														<li class=grouper v-html=group.name></li>
														<li class=choice v-for="cat in group.categories" v-html=cat.name :class="{kbcursor: cat.value == catCursor}" @mousedown.prevent=ChooseCategory(cat.value)></li>
													</template>
												</ol>
												<input class=catamount type=number step=.01 v-model=cat.amount :disabled=!cat.name @focus="editCategory = cat" @keypress=FilterAmountKeys
													@keydown.-=PushSubAmount @keydown.+=PushSubAmount @keydown.*=MultiplySubAmounts @keydown.61=SumSubAmounts @keydown.enter=SumSubAmounts>
											</label>
											<div class=subamounts v-if="subAmounts.length && editCategory == cat">
												<template v-for="(amt, index) in subAmounts">
													<span class=operator v-if=index>{{amt < 0 ? "-" : "+"}}</span>
													<span class=subamount @click=EditSubAmount(index)>{{index && amt < 0 ? (-amt).toFixed(2) : amt.toFixed(2)}}</span>
												</template>
												<span v-if="!subAmountMultiplier && +cat.amount" class=active>
													<span class=operator>{{+cat.amount < 0 ? "-" : "+"}}</span>
													<span class=subamount>{{+cat.amount < 0 ? (-cat.amount).toFixed(2) : (+cat.amount).toFixed(2)}}</span>
												</span>
												<span class=multiplier :class="{active: subAmountMultiplier}" @click=MultiplySubAmounts>
													<span class=operator>×</span>
													<span class=subamount>{{(subAmountMultiplier ? +cat.amount || 1 : 1).toFixed(3)}}</span>
												</span>
												<span class=sum @click=SumSubAmounts>
													<span class=operator>=</span>
													<span class=subamount>{{subAmountTotal}}</span>
												</span>
											</div>
										</template>
										<div class=account :class=transaction.acctclass>{{transaction.acctname}}</div>
										<div class=transdate v-if=transaction.transdate>Transaction <time>{{transaction.transdate}}</time></div>
										<div class=posted>Posted <time>{{transaction.posted}}</time></div>
										<label class=note><input v-model=transaction.notes></label>
										<div class=location v-if=transaction.city>{{transaction.city + (transaction.state ? ', ' + transaction.state + (transaction.zip ? ' ' + transaction.zip : '') : '')}}</div>
									</div>
								</div>
							</li>
						</ul>
					</li>
				</ol>
				<p class=working v-if=loading>Loading transactions...</p>
				<p class=calltoaction v-if=more><a href="#transactions!load" @click.prevent=LoadTransactions>Load more</a></p>
			</main>
		</div>
	`
};
