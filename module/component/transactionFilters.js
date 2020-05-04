import AccountApi from "../api/account.js";
import ReportErrors from "../reportErrors.js";
import FilterAmountKeys from "../filterAmountKeys.js";
import SlideVisible from "../slideVisible.js";
import T from "../transactionShared.js";

const DateCheckInterval = 300000;  // 5 minutes

export default {
	props: [
		'visible',
		'params',
		'catGroups',
		'catsLoaded'
	],
	data() {
		return {
			accounts: [],
			search: "",
			categories: [],
			datestart: "",
			dateend: "",
			minamount: "",
			accountIds: [],
			catSearch: "",
			showSuggestions: false,
			catCursor: false,
			today: "",
			tomorrow: ""
		};
	},
	computed: {
		categoryChoices() {
			const search = this.catSearch ? this.catSearch.trim().toLowerCase() : "";
			const groups = !this.categories.some(c => c.id == 0) && (!search || T.UncategorizedName.toLowerCase().indexOf(search) > -1)
				? [{ name: null, categories: [{ id: 0, value: T.UncategorizedName, name: T.HighlightString(T.UncategorizedName, search) }] }]
				: [];
			for(const group of this.catGroups) {
				let catArr = false;
				if(!search || group.name.toLowerCase().indexOf(search) > -1) {
					for(const cat of group.categories)
						if(!this.categories.some(c => c.id == cat.id)) {
							if(!catArr) {
								catArr = [];
								groups.push({ name: T.HighlightString(group.name, ""), categories: catArr });
							}
							catArr.push({ id: cat.id, value: cat.name, name: T.HighlightString(cat.name, search) });
						}
				} else
					for(const cat of group.categories)
						if(!this.categories.some(c => c.id == cat.id) && (!search || cat.name.toLowerCase().indexOf(search) > -1)) {
							if(!catArr) {
								catArr = [];
								groups.push({ name: T.HighlightString(group.name, ""), categories: catArr });
							}
							catArr.push({ id: cat.id, value: cat.name, name: T.HighlightString(cat.name, search) });
						}
			}
			return groups;
		}
	},
	created() {
		AccountApi.List().done(accounts => {
			this.accounts = accounts.map(a => {
				return { id: a.id, name: a.name, typeClass: a.typeClass };
			});
			this.accounts.sort((a, b) => a.name.toLowerCase().localeCompare(b.name.toLowerCase()));
		}).fail(this.Error);
		this.Reset();
		this.GetDates();
		setInterval(this.GetDates, DateCheckInterval);
	},
	watch: {
		params() {
			this.Reset();
		},
		visible(visible) {
			if(visible)
				setTimeout(() => $("label.search").focus());
		},
		catsLoaded(loaded) {
			if(loaded)
				this.SetCategoriesFromIdList(this.params.cats);
		}
	},
	methods: {
		GetDates() {
			let today = new Date();
			this.today = today.getFullYear() + "-" + ("0" + (today.getMonth() + 1)).slice(-2) + "-" + ("0" + today.getDate()).slice(-2);
			today.setDate(today.getDate() + 1);
			this.tomorrow = today.getFullYear() + "-" + ("0" + (today.getMonth() + 1)).slice(-2) + "-" + ("0" + today.getDate()).slice(-2);
		},
		Reset() {
			this.search = this.params.search || "";
			if(this.catsLoaded)
				this.SetCategoriesFromIdList(this.params.cats);
			this.catSearch = "";
			this.datestart = this.params.datestart || "";
			this.dateend = this.params.dateend || "";
			this.minamount = this.params.minamount || "";
			this.accountIds = this.params.accts ? this.params.accts.split(",").map(id => +id) : []
		},
		SetCategoriesFromIdList(ids) {
			this.categories = [];
			if(ids) {
				if(!Array.isArray(ids))
					ids = ids.split(",");
				for(const id of ids)
					if(!+id)
						this.AddCategory({ id: 0, value: T.UncategorizedName });
					else
						for(const group of this.catGroups)
							for(const cat of group.categories)
								if(cat.id == id)
									this.AddCategory({ id: cat.id, value: cat.name });
			}
		},
		Update() {
			this.$emit("close");
			const params = [];
			if(this.categories.length)
				params.push("cats=" + this.categories.map(c => c.id).join(","));
			if(this.search)
				params.push("search=" + encodeURIComponent(this.search).replace(/%2C/gi, ","));
			if(this.datestart)
				params.push("datestart=" + encodeURIComponent(this.datestart).replace(/%2C/gi, ","));
			if(this.dateend)
				params.push("dateend=" + encodeURIComponent(this.dateend).replace(/%2C/gi, ","));
			if(this.minamount)
				params.push("minamount=" + +this.minamount);
			if(this.accountIds.length)
				params.push("accts=" + this.accountIds.join(","));
			const hash = params.length ? "#transactions!" + params.join("/") : "#transactions";
			if(location.hash != hash)
				location.hash = hash;
		},
		Clear() {
			this.search = "";
			this.categories = [];
			this.catSearch = "";
			this.datestart = "";
			this.dateend = "";
			this.minamount = "";
			this.accountIds = [];
		},
		Cancel() {
			this.Reset();
			this.$emit("close");
		},
		CategoryInput(event) {
			this.catSearch = event.target.value;
			this.showSuggestions = true;
		},
		AddCategory(category) {
			if(category)
				this.categories.push({ id: category.id, name: category.value });
		},
		RemoveCategory(category) {
			this.categories.splice(this.categories.indexOf(category), 1);
		},
		PrevCategory() {
			if(this.categoryChoices.length) {
				this.showSuggestions = true;
				if(this.catCursor) {
					let prev = false;
					for(const group of this.categoryChoices)
						for(const cat of group.categories)
							if(cat == this.catCursor) {
								if(prev)
									this.catCursor = prev;
								else {
									const lastGroup = this.categoryChoices[this.categoryChoices.length - 1];
									this.catCursor = lastGroup.categories[lastGroup.categories.length - 1];
								}
								return;
							}
							else
								prev = cat;
				}
				const lastGroup = this.categoryChoices[this.categoryChoices.length - 1];
				this.catCursor = lastGroup.categories[lastGroup.categories.length - 1];
			}
		},
		NextCategory() {
			if(this.categoryChoices.length) {
				this.showSuggestions = true;
				if(this.catCursor) {
					let found = false;
					for(const group of this.categoryChoices)
						for(const cat of group.categories)
							if(cat == this.catCursor)
								found = true;
							else if(found) {
								this.catCursor = cat;
								return;
							}
				}
				this.catCursor = this.categoryChoices[0].categories[0];
			}
		},
		CategoryBlur() {
			this.HideSuggestions();
		},
		HideSuggestions(event) {
			if(this.showSuggestions) {
				if(event)
					event.stopPropagation();
				this.showSuggestions = false;
				this.catCursor = false;
			}
		}
	},
	mixins: [
		ReportErrors,
		FilterAmountKeys
	],
	directives: {
		slideVisible: SlideVisible
	},
	template: /*html*/ `
		<div id=filters v-slideVisible=visible @keydown.esc=Cancel>
			<label class=search title="Show transactions that have this text in the name">
				<span>Search:</span>
				<input v-model=search maxlength=64 @keydown.enter=Update>
			</label>
			<label class=categories title="Show transactions with these categories">
				Categories:
				<span class="all category" v-if=!categories.length>(all)</span>
				<span class="category" v-for="cat in categories"><span>{{cat.name || "${T.UncategorizedName}"}}</span><a class=remove @click=RemoveCategory(cat)></a></span>
				<input @blur=CategoryBlur @dblclick="showSuggestions = true" @input=CategoryInput :value=catSearch
					@keydown.esc=HideSuggestions @keydown.enter.stop=AddCategory(catCursor) @keydown.tab=AddCategory(catCursor)
					@keydown.up=PrevCategory @keydown.down=NextCategory
					maxlength=24 placeholder="Find a category">
			</label>
			<ol class=suggestions v-if=showSuggestions data-bind="foreach: categoriesForFilter">
				<template v-for="group in categoryChoices">
					<li v-if=group.name class=grouper v-html=group.name></li>
					<li class=choice v-for="cat in group.categories" v-html=cat.name :class="{kbcursor: cat == catCursor}" @mousedown.prevent=AddCategory(cat)></li>
				</template>
			</ol>
			<label class=date title="Show transactions posted on or after this date">
				<span>Since:</span>
				<input type=date :max="dateend || today" v-model=datestart @keydown.enter=Update>
			</label>
			<label class=date title="Show transactions posted on or before this date">
				<span>Before:</span>
				<input type=date :min=datestart :max=tomorrow v-model=dateend @keydown.enter=Update>
			</label>
			<label class=amount title="Show transactions that are at least this amount">
				<span>Min $:</span>
				<input type=number step=.01 min=0 v-model=minamount @keypress=FilterAmountKeys @keydown.enter=Update>
			</label>
			<label class=accounts title="Show transactions from these accounts">
				Accounts:
				<label class=account :class=account.typeClass v-for="account in accounts">
					<input type=checkbox :value=account.id v-model=accountIds>
					{{account.name}}
				</label>
			</label>
			<div class=calltoaction>
				<button @click=Update title="Apply these filters">OK</button>
				<button @click=Clear title="Clear these filters">Clear</button>
				<a href="#transactions!filters" @click.prevent=Cancel title="Go back to the transactions list">Cancel</a>
			</div>
		</div>
	`
};
