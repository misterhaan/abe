import AppName from "../appname.js";

let toastTimeout = false;

export default {
	data() {
		return {
			errors: [],
			toastError: null,
			toastClosing: false,
			showErrors: false,
			now: new Date()
		};
	},
	created() {
		setInterval(() => {
			this.now = new Date();
		}, 1000);
		window.addEventListener("error", e => {
			this.NewError(e.error);
		});
		window.addEventListener("unhandledrejection", e => {
			this.NewError(e.reason);
		});
	},
	methods: {
		NewError(error) {
			if(error) {
				this.ClearToastTimeout();
				error.time = new Date();  // TODO:  use error.time and this.now to show how old each error is
				this.errors.push(error);
				this.toastError = error;
				toastTimeout = setTimeout(() => {
					this.toastError = null;
				}, 5000);
			}
		},
		ToggleErrors() {
			if(!this.showErrors && this.toastError) {
				this.toastClosing = true;
				this.ClearToastTimeout();
				this.toastError = false;
			}
			this.showErrors = !this.showErrors;
		},
		ClearErrors() {
			this.errors.splice(0, this.errors.length);
			this.showErrors = false;
		},
		DismissToast() {
			if(this.toastError) {
				this.toastClosing = true;
				const error = this.toastError;
				this.toastError = null;
				this.ClearToastTimeout();
				this.Dismiss(error);
			}
		},
		ClearToastTimeout() {
			if(toastTimeout) {
				clearTimeout(toastTimeout);
				toastTimeout = false;
			}
		},
		Dismiss(error) {
			this.errors.splice(this.errors.indexOf(error), 1);
			if(!this.errors.length)
				this.showErrors = false;
		}
	},
	template: /*html*/ `
		<footer>
			<Transition name=fade>
				<div id=errorToast v-if=toastError :class="{closing: toastClosing}">
					{{toastError.message}}
					<a class=close title="Dismiss this error" href=#dismissError @click.prevent=DismissToast><span>Dismiss</span></a>
				</div>
			</Transition>
			<div class=errors v-if=showErrors>
				<header>
					{{errors.length }} Error{{errors.length > 1 ? "s" : ""}}
					<a class=minimize title="Minimize the error list" href=#hideErrors @click.prevent=ToggleErrors><span>Minimize</span></a>
					<a class=close title="Dismiss all errors" href=#dismissAllErrrors @click.prevent=ClearErrors><span>Dismiss all</span></a>
				</header>
				<ol class=errors>
					<li v-for="error in errors">
						{{error.message}}
						<a class=close title="Dismiss this error" href=#dismissError @click.prevent=Dismiss(error)><span>Dismiss</span></a>
					</li>
				</ol>
			</div>
			<a class=errors :title="showErrors ? 'Minimize the error list' : 'Show the error list'" v-if=errors.length href=#showErrors @click.prevent=ToggleErrors>{{errors.length}}</a>
			<div id=copyright>© 2017 - 2025 ${AppName.Full}</div>
		</footer>
	`
};
