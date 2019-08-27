import "../../external/d3.min.js";

const Width = 800;
const Height = 400;

export default {
	props: [
		"dates"
	],
	mounted() {
		this.Draw();
	},
	watch: {
		dates() {
			this.Draw();
		}
	},
	methods: {
		Draw() {
			if(this.dates && this.dates.length) {
				const chart = d3.select("#monthtrend");
				chart.html("");
				// amount scale for bar height.  goes from the furthest negative spending to the largest income, with zero somewhere in the middle
				const amount = d3.scaleLinear()
					.domain([d3.min(this.dates.map(d => d.spent)), d3.max(this.dates.map(d => d.made))])
					.range([Height, 0]);
				// render the y axis first so its width can be used in further calculations
				const yaxis = chart.append("g").attr("class", "y axis");
				yaxis.call(d3.axisRight(amount));
				// width of the rendered y axis is the right margin for the rest of the chart
				const right = yaxis.node().getBBox().width;
				// move the axis to the right edge because thats where the most recent date is
				yaxis.attr("transform", `translate(${Width - right},0)`);
				// horizontal grid lines need to be added before the bars to get the bars to cover them
				var grid = chart.append("g").attr("class", "grid").call(d3.axisLeft(amount).tickSize(right - Width));
				grid.selectAll("text").remove();
				grid.select(".domain").remove();
				// date bands scale handles the widths and positions of the bars
				var date = d3.scaleBand().domain(this.dates.map(d => d.name)).rangeRound([0, Width - right]).paddingInner(.125).paddingOuter(.0625);
				// create a set of 3 bars for each date.  net had to come last because it will overlap one of the other two
				var bars = chart.selectAll("g.bars").data(this.dates).enter().append("g").attr("class", "bars").attr("transform", d => `translate(${date(d.name)},0)`);
				bars.append("title").text(d => d.name + ":\n" + d.made + " income\n" + d.spent + " spending\n" + d.net + " net");
				bars.append("rect").attr("class", "made").attr("y", d => amount(d.made)).attr("height", d => amount(0) - amount(d.made)).attr("width", date.bandwidth());
				bars.append("rect").attr("class", "spent").attr("y", amount(0)).attr("height", d => amount(d.spent) - amount(0)).attr("width", date.bandwidth());
				bars.append("rect").attr("class", d => d.net < 0 ? "net loss" : "net gain").attr("y", d => Math.min(amount(0), amount(d.net))).attr("height", d => Math.abs(amount(d.net) - amount(0))).attr("width", date.bandwidth());
				// render the x axis last so it's visible on top of the bars
				chart.append("g").attr("class", "x axis").attr("transform", `translate(0,${amount(0)})`).call(d3.axisBottom(date).tickSizeOuter(0));
			}
		}
	},
	template: /*html*/ `
		<svg id=monthtrend viewBox="0 0 ${Width} ${Height}"></svg>
	`
};
