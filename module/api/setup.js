import ApiBase from "./apiBase.js";

const urlbase = "api/setup/";

export default class SetupApi extends ApiBase {
	static Level() {
		return super.GET(urlbase + "level");
	}
	static ConfigureDatabase(host, name, user, pass) {
		return super.POST(urlbase + "configureDatabase", {
			host: host,
			name: name,
			user: user,
			pass: pass
		});
	}
	static CreateDatabase(rootpw) {
		return super.POST(urlbase + "createDatabase", { rootpw: rootpw });
	}
	static InstallDatabase() {
		return super.POST(urlbase + "installDatabase");
	}
	static UpgradeDatabase() {
		return super.POST(urlbase + "upgradeDatabase");
	}
};
