import Vue from "../external/vue.esm.browser.min.js";
import Views from "./views.js";
import TitleBar from "./component/titlebar.js";
import StatusBar from "./component/statusbar.js";
import Home from "./component/home.js";

new Vue({
	el: "#abe",
	data: {
		view: Views.Home,
		params: false
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
		ParseHash(this);
	},
	methods: {
		ChangeView(view, params = false) {
			this.params = params;
			this.view = view;
		},
		OnError(error) {
			// TODO:  put errors into the status bar
			alert(error.message || error);
		}
	},
	components: {
		titlebar: TitleBar,
		statusbar: StatusBar,
		[Views.Home.Name]: Home
	}
});

function ParseHash(abe) {
	let hash = window.location.hash.substring(1).split("!");
	let viewPieces = hash[0].split("/");
	let viewName = viewPieces.shift();
	let view = false;
	for(let v in Views) {
		let realView = Views[v];
		if(realView.Name == viewName)
			view = realView;
	}
	if(view) {
		let params = {};
		if(hash.length > 1) {
			let paramlist = hash[1].split("/");
			for(let p in paramlist) {
				let pair = paramlist[p].split("=");
				if(pair.length > 1)
					params[pair.shift()] = pair.join("-");
			}
		}
		if(viewPieces.length)
			params.subview = viewPieces.join("/");
		abe.ChangeView(view, params);
	}
}
