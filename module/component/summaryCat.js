import "../../external/d3.min.js";

const Width = 800;
const Height = 400;

export default {
	props: [
		"cats",
		"dates"
	],
	mounted() {
		this.Draw();
	},
	watch: {
		cats() {
			this.Draw();
		},
		dates() {
			this.Draw();
		}
	},
	methods: {
		Draw() {
			if(this.dates && this.dates.length) {
				const pad = 5;  // padding for the legend
				const axisheight = 17;  // height required above y = 0 to draw the axis
				const chart = d3.select("#monthcattrend");
				chart.html("");
				// get the category data into a better structure
				var data = this.cats.map(c => {
					return {
						id: c.id,
						name: c.name,
						hidden: false,
						values: this.dates.map(d => {
							if(c.subcats) {
								var amt = 0;
								for(var s = 0; s < c.subcats.length; s++)
									if(d.cats[c.subcats[s].id])
										amt += +d.cats[c.subcats[s].id];
								return {
									date: d.name,
									amount: amt
								};
							} else
								return {
									date: d.name,
									amount: d.cats[c.id] ? +d.cats[c.id] : 0
								};
						})
					};
				});
				// amount scale for y axis
				const amount = d3.scaleLinear().range([Height, 0])
					.domain([d3.min(data, c => d3.min(c.values, v => v.amount)), d3.max(data, c => d3.max(c.values, v => v.amount))]);
				amount.range([Height, Math.max(0, axisheight - amount(0))]);
				// render the y axis first so its width can be used in further calculations
				const yaxis = chart.append("g").attr("class", "y axis");
				yaxis.call(d3.axisRight(amount));
				// width of the rendered y axis is the right margin for the rest of the chart
				const right = yaxis.node().getBBox().width;
				// keep the same scale position as the other chart if it exists
				const barRight = d3.select("#monthtrend g.axis.y");
				if(barRight.size()) {
					barRight = barRight.node().getBBox().width;
					if(barRight > right)
						right = barRight;
				}
				// move the axis to the right edge because thats where the most recent date is
				yaxis.attr("transform", "translate(" + (Width - right) + ",0)");
				// horizontal grid lines need to be added before the bars to get the bars to cover them
				const grid = chart.append("g").attr("class", "grid").call(d3.axisLeft(amount).tickSize(right - Width));
				grid.selectAll("text").remove();
				grid.select(".domain").remove();
				// 10 different colors for categories
				const color = d3.scaleOrdinal(d3.schemeCategory10)
					.domain(data.map(c => c.id));
				// date scale for x axis.  bands to match the other graph
				const date = d3.scaleBand()
					.domain(data[0].values.map(v => v.date))
					.rangeRound([0, Width - right]).paddingInner(.125).paddingOuter(.0625);
				const line = d3.line().curve(d3.curveMonotoneX).x(c => date(c.date)).y(c => amount(c.amount));
				// draw the lines
				const lines = chart.append("g").attr("class", "catlines").attr("transform", `translate(${date.bandwidth() / 2},0)`).selectAll("path").data(data);
				lines.enter().append("path").style("stroke", c => color(c.id)).style("fill", "none").attr("id", c => "line" + c.id).attr("d", c => line(c.values)).append("title").text(c => c.name);
				lines.exit().remove();
				// render the x axis last so it's visible on top of the lines
				chart.append("g").attr("class", "x axis").attr("transform", `translate(0,${amount(0)})`).call(d3.axisTop(date).tickSizeOuter(0));
				// legend with selection capabilities
				const legend = chart.append("g").attr("class", "legend").attr("transform", "translate(3,3)");
				const legbg = legend.append("rect").attr("rx", 5).attr("ry", 5).attr("opacity", .8);
				const legcats = legend.selectAll("g.legcat").data(data).enter()
					.append("g").attr("class", "legcat").attr("transform", (c, i) => `translate(${pad},${14 * (i + 1)})`).on("click", function(c) {
						// toggle whether this category is hidden
						c.hidden = !c.hidden;
						this.classList.toggle("deselected");
						this.querySelector("title").textContent = c.hidden ? `Include ${c.name}` : `Exclude ${c.name}`;
						// update amount scale and redraw lines with it
						amount.range([Height, 0]).domain([d3.min(data, c => c.hidden ? 0 : d3.min(c.values, v => v.amount)), d3.max(data, c => c.hidden ? 0 : d3.max(c.values, v => v.amount))]);
						amount.range([Height, Math.max(0, axisheight - amount(0))]);
						chart.selectAll(".catlines path").data(data).transition().style("opacity", c => c.hidden ? 0 : 1).attr("d", c => line(c.values));
						// redraw axes
						yaxis.transition().call(d3.axisRight(amount));
						grid.transition().call(d3.axisLeft(amount).tickSize(right - Width));
						grid.selectAll("text").remove();
						grid.select(".domain").remove();
						chart.select(".axis.x").transition().attr("transform", `translate(0,${amount(0)})`);
					});
				legcats.append("rect").attr("y", -8.5).attr("width", 10).attr("height", 10).attr("rx", 2).attr("ry", 2).attr("stroke", c => color(c.id)).attr("fill", c => color(c.id));
				legcats.append("text").text(c => c.name).attr("x", 15).attr("font-size", "10");
				legcats.append("title").text(c => `Exclude ${c.name}`);
				legbg.attr("width", legend.node().getBBox().width + 2 * pad).attr("height", legend.node().getBBox().height + 1.5 * pad);
			}
		}
	},
	template: /*html*/ `
		<svg id=monthcattrend viewBox="0 0 ${Width} ${Height}"></svg>
	`
};
