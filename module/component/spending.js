import SummaryApi from "../api/summary.js";
import SummaryNet from "./summaryNet.js";
import SummaryCat from "./summaryCat.js";
import SummaryDet from "./summaryDet.js";
import ReportErrors from "../reportErrors.js";

const DefaultSize = "monthly";
const DefaultType = "net";

export default {
	props: [
		"params"
	],
	data() {
		return {
			dates: [],
			cats: []
		};
	},
	computed: {
		type() {
			return this.params && this.params.type ? this.params.type : DefaultType;
		},
		size() {
			return this.params && this.params.size ? this.params.size : DefaultSize;
		},
		summary() {
			return "summary" + this.type.charAt(0).toUpperCase() + this.type.slice(1);
		}
	},
	created() {
		this.Load();
	},
	watch: {
		size() {
			this.Load();
		}
	},
	methods: {
		SetSize(newSize) {
			const querystring = [];
			if(newSize != DefaultSize)
				querystring.push("size=" + encodeURIComponent(newSize));
			if(this.type != DefaultType)
				querystring.push("type=" + encodeURIComponent(this.type));
			if(querystring.length)
				location = "#spending!" + querystring.join("/");
			else
				location = "#spending";
		},
		SetType(newType) {
			const querystring = [];
			if(this.size != DefaultSize)
				querystring.push("size=" + encodeURIComponent(this.size));
			if(newType != DefaultType)
				querystring.push("type=" + encodeURIComponent(newType));
			if(querystring.length)
				location = "#spending!" + querystring.join("/");
			else
				location = "#spending";
		},
		Load() {
			(this.size == "yearly"
				? SummaryApi.YearlyCategories()
				: SummaryApi.MonthlyCategories()
			).done(results => {
				this.dates = results.dates;
				this.cats = results.cats;
			}).fail(this.Error);
		}
	},
	mixins: [ReportErrors],
	components: {
		summaryNet: SummaryNet,
		summaryCat: SummaryCat,
		summaryDet: SummaryDet
	},
	template: /*html*/ `
		<div id=spending>
			<header id=pagesettings>
				<div>
					<label><input type=radio name=summarysize value=monthly :checked="size == 'monthly'" @change="SetSize('monthly')"><span title="Summarize data by month">Monthly</span></label>
					<label><input type=radio name=summarysize value=yearly :checked="size == 'yearly'" @change="SetSize('yearly')"><span title="Summarize data by year">Yearly</span></label>
				</div>
				<div>
					<label><input type=radio name=summarytype value=net :checked="type == 'net'" @change="SetType('net')"><span title="Bar graph of overall income and spending"></span></label>
					<label><input type=radio name=summarytype value=cat :checked="type == 'cat'" @change="SetType('cat')"><span title="Line graph of category spending"></span></label>
					<label><input type=radio name=summarytype value=det :checked="type == 'det'" @change="SetType('det')"><span title="Table of category spending amounts"></span></label>
				</div>
			</header>
			<component :is=summary :dates=dates :cats=cats></component>
		</div>
	`
};
