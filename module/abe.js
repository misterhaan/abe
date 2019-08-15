import Vue from "../external/vue.esm.browser.min.js";
import $ from "../external/jquery-3.4.1.min.js";
import Views from "./views.js";
import TitleBar from "./component/titlebar.js";
import StatusBar from "./component/statusbar.js";
import Home from "./component/home.js";
import Saving from "./component/saving.js";

new Vue({
	el: "#abe",
	data: {
		view: Views.Home,
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
			document.title = val.Title;
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
				for(let v in Views) {
					let realView = Views[v];
					if(realView.Name == viewName)
						view = realView;
				}
				if(view) {
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
					if(viewPieces.length)
						params.subview = viewPieces.join("/");
					if(this.view != view || (params === false) != (this.params === false) || new URLSearchParams(this.params).toString() != new URLSearchParams(params).toString())
						this.ChangeView(view, params);
				}
			}
		},

		ChangeView(view, params = false) {
			this.actions = [];
			this.params = params;
			this.view = view;
		},
		OnAddAction(action) {
			this.actions.push(action);
		},
		OnError(error) {
			// TODO:  put errors into the status bar
			alert(error.message || error);
		}
	},
	components: {
		titlebar: TitleBar,
		statusbar: StatusBar,
		[Views.Home.Name]: Home,
		[Views.Saving.Name]: Saving
	}
});
