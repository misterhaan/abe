import AppName from "./appname.js";

export default {
	Home: {
		Name: "home",
		Title: AppName.Full
	},
	Transactions: {
		Name: "transactions",
		Title: "Transactions",
		BookmarkParams: ["cats", "search", "datestart", "dateend", "minamount", "accts"]
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
