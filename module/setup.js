import Vue from "../external/vue.esm.browser.min.js";
import AppName from "./appname.js";
import TitleBar from "./component/titlebar.js";
import StatusBar from "./component/statusbar.js";
import SetupApi from "./api/setup.js";
import ReportErrors from "./reportErrors.js";

const SetupLevel = {
	Unknown: -99,
	FreshInstall: -4,
	DatabaseConnectionDefined: -3,
	DatabaseExists: -2,
	DatabaseInstalled: -1,
	DatabaseUpToDate: 0
};

const setup = new Vue({
	el: "#abe",
	data: {
		level: SetupLevel.Unknown,
		error: false
	},
	computed: {
		step() {
			switch(this.level) {
				case SetupLevel.FreshInstall: return "configureDatabase";
				case SetupLevel.DatabaseConnectionDefined: return "createDatabase";
				case SetupLevel.DatabaseExists: return "installDatabase";
				case SetupLevel.DatabaseInstalled: return "upgradeDatabase";
				case SetupLevel.DatabaseUpToDate: return "setupComplete";
				default: return "setupChecking";
			}
		}
	},
	created() {
		SetupApi.Level().done(level => {
			this.level = level;
		});  // this API call has no failure cases
	},
	components: {
		titlebar: TitleBar,
		statusbar: StatusBar,
		setupChecking: {
			template: /*html*/ `
				<main role=main>
					<h2>Setup Initializing</h2>
					<div class=percentfield></div>
					<p class=working>Checking current setup progress</p>
				</main>
			`
		},
		configureDatabase: {
			data() {
				return {
					host: "localhost",
					name: "abe",
					user: "",
					pass: "",
					showPass: false,
					working: false
				};
			},
			computed: {
				enabled() {
					return !this.working && this.host && this.name && this.user && this.pass;
				}
			},
			methods: {
				Save() {
					this.working = true;
					SetupApi.ConfigureDatabase(this.host, this.name, this.user, this.pass).done(() => {
						this.$emit("set-level", SetupLevel.DatabaseConnectionDefined);
					}).fail(this.Error).always(() => {
						this.working = false;
					});
				},
			},
			mixins: [ReportErrors],
			template: /*html*/ `
				<main role=main class=setup>
					<h2>Database Configuration</h2>
					<div class=percentfield><div class=percentvalue style="width: 0"></div></div>
					<p>
						${AppName.Full} stores data in a MySQL database.  Set up the
						connection below:
					</p>
					<label title="Enter the hostname for the database.  Usually the database is the same host as the web server, and the hostname should be 'localhost'">
						<span class=label>Host:</span>
						<span class=field><input v-model.trim=host required></span>
					</label>
					<label title="Enter the name of the database ${AppName.Short} should use">
						<span class=label>Database:</span>
						<span class=field><input v-model.trim=name required></span>
					</label>
					<label title="Enter the username that owns the ${AppName.Short} database">
						<span class=label>Username:</span>
						<span class=field><input v-model.trim=user required></span>
					</label>
					<label title="Enter the password for the user that owns the ${AppName.Short} database">
						<span class=label>Password:</span>
						<span class=field>
							<input :type="showPass ? 'text' : 'password'" v-model=pass required>
							<a href=#togglePassword :class="showPass ? 'hide' : 'show'" :title="showPass ? 'Hide the password' : 'Show the password'" @click.prevent="showPass = !showPass"></a>
						</span>
					</label>
					<nav class=calltoaction><button :disabled=!enabled :class="{working: working}" @click=Save>Save</button></nav>
				</main>
			`
		},
		createDatabase: {
			data() {
				return {
					password: "",
					working: false
				};
			},
			methods: {
				Create() {
					this.working = true;
					SetupApi.CreateDatabase(this.password).done(() => {
						this.$emit("set-level", SetupLevel.DatabaseExists);
					}).fail(this.Error).always(() => {
						this.working = false;
					});
				}
			},
			mixins: [ReportErrors],
			template: /*html*/ `
				<main role=main class=setup>
					<h2>Create Database</h2>
					<div class=percentfield><div class=percentvalue style="width: 33.333%"></div></div>
					<p>
						${AppName.Full} can’t connect to the database.  Usually this is
						because the database hasn’t been created yet.  If your MySQL server
						is set up with a root password, enter it below to create the Abe
						database and grant access to the configured user.  Alternately, run
						the two queries listed below as root and reload this page to move on.
					</p>
					<label title="Enter the password for the MySQL root user (will not be stored)">
						<span class=label>Password:</span>
						<span class=field><input type=password required v-model.trim=password></span>
					</label>
					<nav class=calltoaction><button :disabled="working || !password" :class="{working: working}" @click=Create>Create</button></nav>
					<p>
						Make sure to change \`abe\` below to your database name, USERNAME
						to your username, and PASSWORD to your password.  Then copy and
						paste into a root MySQL prompt.
					</p>
					<textarea>create database \`abe\` character set utf8mb4 collate utf8mb4_unicode_ci;
grant all on \`abe\`.* to 'USERNAME'@'localhost' identified by 'PASSWORD';</textarea>
				</main>
			`
		},
		installDatabase: {
			data() {
				return {
					working: false
				};
			},
			methods: {
				Install() {
					this.working = true;
					SetupApi.InstallDatabase().done(() => {
						this.$emit("set-level", SetupLevel.DatabaseUpToDate);  // install always goes straight to the latest version
					}).fail(this.Error).always(() => {
						this.working = false;
					});
				}
			},
			mixins: [ReportErrors],
			template: /*html*/ `
				<main role=main class=setup>
					<h2>Install Database</h2>
					<div class=percentfield><div class=percentvalue style="width: 66.667%"></div></div>
					<p>
						It looks like the ${AppName.Short} database hasn’t been installed.
					</p>
					<nav class=calltoaction><button :disabled=working :class="{working: working}" @click=Install>Install Database</button></nav>
				</main>
			`
		},
		upgradeDatabase: {
			data() {
				return {
					working: false
				};
			},
			methods: {
				Upgrade() {
					this.working = true;
					SetupApi.UpgradeDatabase().done(() => {
						this.$emit("set-level", SetupLevel.DatabaseUpToDate);
					}).fail(this.Error).always(() => {
						this.working = false;
					});
				}
			},
			mixins: [ReportErrors],
			template: /*html*/ `
				<main role=main class=setup>
					<h2>Upgrade Database</h2>
					<div class=percentfield><div class=percentvalue style="width: 75%"></div></div>
					<p>
						There have been some additions to ${AppName.Full} since this
						database was last set up.  An upgrade is needed to activate them
						and keep everything else running smoothly.
					</p>
					<nav class=calltoaction><button :disabled=working :class="{working: working}" @click=Upgrade>Upgrade Database</button></nav>
				</main>
			`
		},
		setupComplete: {
			template: /*html*/ `
				<main role=main class=setup>
					<h2>Setup Complete!</h2>
					<div class=percentfield><div class=percentvalue></div></div>
					<p>
						The ${AppName.Short} database is fully up-to-date!  If this is your
						first time using ${AppName.Full}, you should start by adding your
						accounts.
					</p>
					<nav class=calltoaction>
						<a href=.>To the Main Menu!</a>
						<a href=.#settings/accounts>To accounts!</a>
					</nav>
				</main>
			`
		}
	}
});
