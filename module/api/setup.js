import ApiBase from "./apiBase.js";

const urlbase = "api/setup/";

export default class SetupApi extends ApiBase {
	static Level() {
		return super.GET(urlbase + "level", result => result.level);
	}
	static ConfigureDatabase(host, name, user, pass) {
		return super.POST(urlbase + "configureDatabase", {
			host: host,
			name: name,
			user: user,
			pass: pass
		}, () => true);
	}
	static CreateDatabase(rootpw) {
		return super.POST(urlbase + "createDatabase", { rootpw: rootpw }, () => true);
	}
	static InstallDatabase() {
		return super.POST(urlbase + "installDatabase", {}, () => true);
	}
	static UpgradeDatabase() {
		return super.POST(urlbase + "upgradeDatabase", {}, () => true);
	}
};
