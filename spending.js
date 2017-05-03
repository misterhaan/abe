$(function() {
	ko.applyBindings(MonthsVM = new MonthsViewModel());
});

function MonthsViewModel() {
	var self = this;
	this.cats = ko.observableArray([]);
	this.months = ko.observableArray([]);
	this.months.subscribe(function() {
		var data = MonthsVM.months();
		var svg = $("#monthtrend");
		if(!svg[0].initialized) {
			var viewbox = svg[0].viewBox.baseVal;
			// amount scale for bar height.  goes from the furthest negative spending to the largest income, with zero somewhere in the middle
			var amount = d3.scaleLinear().domain([d3.min(data.map(function(m) {return m.spent;})), d3.max(data.map(function(m) {return m.made;}))]).range([viewbox.height, 0]);
			var chart = d3.select("#monthtrend");
			// render the y axis first so its width can be used in further calculations
			var yaxis = chart.append("g").attr("class", "y axis");
			yaxis.call(d3.axisRight(amount));
			// width of the rendered y axis is the right margin for the rest of the chart
			var right = yaxis.node().getBBox().width;
			// move the axis to the right edge because thats where the most recent month is
			yaxis.attr("transform", "translate(" + (viewbox.width - right) + ",0)");
			// horizontal grid lines need to be added before the bars to get the bars to cover them
			var grid = chart.append("g").attr("class", "grid").call(d3.axisLeft(amount).tickSize(right - viewbox.width));
			grid.selectAll("text").remove();
			grid.select(".domain").remove();
			// month bands scale handles the widths and positions of the bars
			var month = d3.scaleBand().domain(data.map(function(m) {return m.name;})).rangeRound([0, viewbox.width - right]).paddingInner(.125).paddingOuter(.0625);
			// create a set of 3 bars for each month.  net had to come last because it will overlap one of the other two
			var bars = chart.selectAll("g.bars").data(data).enter().append("g").attr("class", "bars").attr("transform", function(m) {return "translate(" + month(m.name) + ",0)";});
			bars.append("title").text(function(m) {
				return m.name + ":\n" + m.made + " income\n" + m.spent + " spending\n" + m.net + " net";
			});
			bars.append("rect").attr("class", "made").attr("y", function(m) {return amount(m.made);}).attr("height", function(m) {return amount(0) - amount(m.made);}).attr("width", month.bandwidth());
			bars.append("rect").attr("class", "spent").attr("y", amount(0)).attr("height", function(m) {return amount(m.spent) - amount(0);}).attr("width", month.bandwidth());
			bars.append("rect").attr("class", function(m) {return m.net < 0 ? "net loss" : "net gain";}).attr("y", function(m) {return Math.min(amount(0), amount(m.net));}).attr("height", function(m) {return Math.abs(amount(m.net) - amount(0));}).attr("width", month.bandwidth());
			// render the x axis last so it's visible on top of the bars
			chart.append("g").attr("class", "x axis").attr("transform", "translate(0," + amount(0) + ")").call(d3.axisBottom(month));
			svg[0].initialized = true;
		}
	});

	this.Load = function() {
		$.get("?ajax=monthcats", {}, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				self.cats(result.cats);
				self.months(result.months);
			}
		}, "json");
	};
	this.Load();
}
