import Vue from "../external/vue.esm.browser.min.js";
import $ from "../external/jquery-3.4.1.min.js";
import AppName from "./appname.js";
import Views from "./views.js";
import TitleBar from "./component/titlebar.js";
import StatusBar from "./component/statusbar.js";
import Home from "./component/home.js";
import Import from "./component/import.js";
import Saving from "./component/saving.js";
import Settings from "./component/settings.js";

new Vue({
	el: "#abe",
	data: {
		view: Views.Home,
		subView: false,
		params: false,
		actions: []
	},
	computed: {
		isHome() {
			return this.view == Views.Home;
		}
	},
	watch: {
		view(val) {
			let title = val.Title;
			if(val != Views.Home) {
				title += " - " + AppName.Short;
				if(val.SubViews)
					if(this.subView)
						title = this.subView.Title + " - " + title;
					else
						for(let v in val.SubViews)
							if(val.SubViews[v].Name == val.DefaultSubViewName) {
								title = val.SubViews[v].Title + " - " + title;
								break;
							}
			}
			document.title = title;
		}
	},
	created() {
		this.ParseHash();
		$(window).on("hashchange", this.ParseHash);
	},
	methods: {
		ParseHash() {
			if(window.location.hash == "" || window.location.hash == "#")
				this.ChangeView(Views.Home);
			else {
				let hash = window.location.hash.substring(1).split("!");
				let viewPieces = hash.shift().split("/");
				let viewName = viewPieces.shift();
				let view = false;
				for(let v in Views)
					if(Views[v].Name == viewName) {
						view = Views[v];
						break;
					}
				if(view) {
					let subView = false;
					if(view.SubViews) {
						let subViewName = viewPieces.shift() || view.DefaultSubViewName;
						for(let sv in view.SubViews)
							if(view.SubViews[sv].Name == subViewName) {
								subView = view.SubViews[sv];
								break;
							}
					}

					let params = false;
					if(hash.length) {
						params = [];
						let paramlist = hash.join("!").split("/");
						for(let p in paramlist) {
							let pair = paramlist[p].split("=");
							if(pair.length > 1)
								params[pair.shift()] = pair.join("-");
						}
					}

					if(this.view != view || this.subView != subView || (params === false) != (this.params === false) || new URLSearchParams(this.params).toString() != new URLSearchParams(params).toString())
						this.ChangeView(view, subView, params);
				}
			}
		},

		ChangeView(view, subView = false, params = false) {
			this.actions = [];
			this.params = params;
			this.subView = subView;
			this.view = view;
		},
		OnAddAction(action) {
			this.actions.push(action);
		},
		OnError(error) {
			// TODO:  put errors into the status bar instead
			alert(error.message || error);
		}
	},
	components: {
		titlebar: TitleBar,
		statusbar: StatusBar,
		[Views.Home.Name]: Home,
		[Views.Import.Name]: Import,
		[Views.Saving.Name]: Saving,
		[Views.Settings.Name]: Settings
	}
});
