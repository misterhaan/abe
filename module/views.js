import AppName from "./appname.js";

export default {
	Home: {
		Name: "home",
		Title: AppName.Full
	},
	Import: {
		Name: "import",
		Title: "Import"
	},
	Saving: {
		Name: "saving",
		Title: "Saving"
	},
	Settings: {
		Name: "settings",
		Title: "Settings",
		DefaultSubViewName: "categories",
		SubViews: {
			Accounts: {
				Name: "accounts",
				Title: "Accounts"
			},
			Categories: {
				Name: "categories",
				Title: "Categories"
			}
		}
	}
};
