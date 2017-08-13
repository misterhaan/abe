$(function() {
	ko.applyBindings(MonthsVM = new MonthsViewModel());
});

function MonthsViewModel() {
	var self = this;
	this.cats = ko.observableArray([]);
	this.expandedCats = ko.observableArray([]);
	this.months = ko.observableArray([]);
	this.months.subscribe(function() {
		InitBarGraph();
		InitLineGraph();
		FixedTable();
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

	this.findParentAmount = function(m, parent) {
		var subcats = false;
		for(var p = 0; p < self.cats().length && !subcats; p++)
			if(self.cats()[p].id == parent)
				subcats = self.cats()[p].subcats;
		if(subcats) {
			var amount = 0;
			for(var s = 0; s < subcats.length; s++)
				if(self.months()[m].cats[subcats[s].id])
					amount += +self.months()[m].cats[subcats[s].id];
			return amount ? amount.toFixed(2) : "";
		}
		return "";
	};

	this.ToggleCategory = function(category) {
		var i = self.expandedCats().indexOf(category.id);
		if(i > -1)
			self.expandedCats.splice(i, 1);
		else
			self.expandedCats.push(category.id);
		FixedTable();
	};
}

/**
 * Initialize the monthly spending bar graph.
 */
function InitBarGraph() {
	var chart = d3.select("#monthtrend");
	var months = MonthsVM.months();
	var viewbox = $("#monthtrend")[0].viewBox.baseVal;
	// amount scale for bar height.  goes from the furthest negative spending to the largest income, with zero somewhere in the middle
	var amount = d3.scaleLinear()
		.domain([d3.min(months.map(function(m) {return m.spent;})), d3.max(months.map(function(m) {return m.made;}))])
		.range([viewbox.height, 0]);
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
	var month = d3.scaleBand().domain(months.map(function(m) {return m.name;})).rangeRound([0, viewbox.width - right]).paddingInner(.125).paddingOuter(.0625);
	// create a set of 3 bars for each month.  net had to come last because it will overlap one of the other two
	var bars = chart.selectAll("g.bars").data(months).enter().append("g").attr("class", "bars").attr("transform", function(m) {return "translate(" + month(m.name) + ",0)";});
	bars.append("title").text(function(m) {
		return m.name + ":\n" + m.made + " income\n" + m.spent + " spending\n" + m.net + " net";
	});
	bars.append("rect").attr("class", "made").attr("y", function(m) {return amount(m.made);}).attr("height", function(m) {return amount(0) - amount(m.made);}).attr("width", month.bandwidth());
	bars.append("rect").attr("class", "spent").attr("y", amount(0)).attr("height", function(m) {return amount(m.spent) - amount(0);}).attr("width", month.bandwidth());
	bars.append("rect").attr("class", function(m) {return m.net < 0 ? "net loss" : "net gain";}).attr("y", function(m) {return Math.min(amount(0), amount(m.net));}).attr("height", function(m) {return Math.abs(amount(m.net) - amount(0));}).attr("width", month.bandwidth());
	// render the x axis last so it's visible on top of the bars
	chart.append("g").attr("class", "x axis").attr("transform", "translate(0," + amount(0) + ")").call(d3.axisBottom(month).tickSizeOuter(0));
}

/**
 * Initialize the monthly category line graph.
 */
function InitLineGraph() {
	var pad = 5;  // padding for the legend
	var axisheight = 17;  // height required above y = 0 to draw the axis
	var chart = d3.select("#monthcattrend");
	var viewbox = $("#monthcattrend")[0].viewBox.baseVal;
	// get the category data into a better structure
	var data = MonthsVM.cats().map(function(c) {
		return {
			id: c.id,
			name: c.name,
			hidden: false,
			values: MonthsVM.months().map(function(m) {
				if(c.subcats) {
					var amt = 0;
					for(var s = 0; s < c.subcats.length; s++)
						if(m.cats[c.subcats[s].id])
							amt += +m.cats[c.subcats[s].id];
					return {
						month: m.name,
						amount: amt
					};
				} else
					return {
						month: m.name,
						amount: m.cats[c.id] ? +m.cats[c.id] : 0
					};
			})
		};
	});
	// amount scale for y axis
	var amount = d3.scaleLinear().range([viewbox.height, 0])
		.domain([d3.min(data, function(c) {return d3.min(c.values, function(v) {return v.amount;});}), d3.max(data, function(c) {return d3.max(c.values, function(v) {return v.amount;});})]);
	amount.range([viewbox.height, Math.max(0, axisheight - amount(0))]);
	// render the y axis first so its width can be used in further calculations
	var yaxis = chart.append("g").attr("class", "y axis");
	yaxis.call(d3.axisRight(amount));
	// width of the rendered y axis is the right margin for the rest of the chart
	var right = yaxis.node().getBBox().width;
	// keep the same scale position as the other chart
	var barRight = d3.select("#monthtrend g.axis.y").node().getBBox().width;
	if(barRight > right)
		right = barRight;
	// move the axis to the right edge because thats where the most recent month is
	yaxis.attr("transform", "translate(" + (viewbox.width - right) + ",0)");
	// horizontal grid lines need to be added before the bars to get the bars to cover them
	var grid = chart.append("g").attr("class", "grid").call(d3.axisLeft(amount).tickSize(right - viewbox.width));
	grid.selectAll("text").remove();
	grid.select(".domain").remove();
	// 10 different colors for categories
	var color = d3.scaleOrdinal(d3.schemeCategory10)
		.domain(data.map(function(c) {return c.id;}));
	// month scale for x axis.  bands to match the other graph
	var month = d3.scaleBand()
		.domain(data[0].values.map(function(v) {return v.month;}))
		.rangeRound([0, viewbox.width - right]).paddingInner(.125).paddingOuter(.0625);
	var line = d3.line().curve(d3.curveMonotoneX).x(function(c) {return month(c.month);}).y(function(c) {return amount(c.amount);});
	// draw the lines
	var lines = chart.append("g").attr("class", "catlines").attr("transform", "translate(" + (month.bandwidth() / 2) + ",0)").selectAll("path").data(data);
	lines.enter().append("path").style("stroke", function(c) {return color(c.id);}).style("fill", "none").attr("id", function(c) {return "line" + c.id;}).attr("d", function(c) {return line(c.values);}).append("title").text(function(c) {return c.name;});
	lines.exit().remove();
	// render the x axis last so it's visible on top of the lines
	chart.append("g").attr("class", "x axis").attr("transform", "translate(0," + amount(0) + ")").call(d3.axisTop(month).tickSizeOuter(0));
	// legend with selection capabilities
	var legend = chart.append("g").attr("class", "legend").attr("transform", "translate(3,3)");
	var legbg = legend.append("rect").attr("rx", 5).attr("ry", 5).attr("opacity", .8);
	legend.selectAll("g.legcat").data(data).enter()
		.append("g").attr("class", "legcat").attr("transform", function(c, i) {return "translate(" + pad + "," + (14 * (i + 1)) + ")";})
			.append("text").text(function(c) {return c.name;}).attr("font-size", "10").attr("fill", function(c) {return color(c.id);}).on("click", function(c) {
				// toggle whether this category is hidden
				c.hidden = !c.hidden;
				$(this).toggleClass("deselected")
					.find("title").text(c.hidden ? "Include " + c.name : "Remove " + c.name);
				// update amount scale and redraw lines with it
				amount.range([viewbox.height, 0]).domain([d3.min(data, function(c) {return c.hidden ? 0 : d3.min(c.values, function(v) {return v.amount;});}), d3.max(data, function(c) {return c.hidden ? 0: d3.max(c.values, function(v) {return v.amount;});})]);
				amount.range([viewbox.height, Math.max(0, axisheight - amount(0))]);
				chart.selectAll(".catlines path").data(data).transition().style("opacity", function(c) {return c.hidden ? 0 : 1;}).attr("d", function(c) {return line(c.values);});
				// redraw axes
				yaxis.transition().call(d3.axisRight(amount));
				grid.transition().call(d3.axisLeft(amount).tickSize(right - viewbox.width));
				grid.selectAll("text").remove();
				grid.select(".domain").remove();
				chart.select(".axis.x").transition().attr("transform", "translate(0," + amount(0) + ")");
			}).append("title").text(function(c) {return "Remove " + c.name;});
	legbg.attr("width", legend.node().getBBox().width + 2 * pad).attr("height", legend.node().getBBox().height + 2 * pad);
}

/**
 * Freeze first row and column of the table
 */
function FixedTable() {
	// fix table top and left headers
	setTimeout(function() {
		var div = $("#spendmonthcat > div");
		div.animate({scrollLeft: 0}, 250);
		var width = div.find("thead tr td")[0].getBoundingClientRect().width + "px";
		var height = div.find("thead tr td")[0].getBoundingClientRect().height + "px";
		div.find("header").remove();
		var scroll = {top: div.scrollTop(), left: div.scrollLeft()};
		var top = $("<header class=top>")
			.css({top: scroll.top, left: width, height: height});
		var left = $("<header class=left>")
			.css({top: height, left: scroll.left, width: width});
		var corner = $("<header class=corner>")
			.css({top: scroll.top, left: scroll.left, width: width, height: height});
		div.append(top);
		div.append(left);
		div.append(corner);
		div.find("thead th").each(function() {
			top.append($("<div class=h>").text($(this).text()).css("width", $(this).width() + "px"));
		});
		div.find("tbody th").each(function() {
			var h = $("<div class=h>").text($(this).text());
			if($(this).parent().hasClass("total"))
				h.addClass("total");
			if($(this).parent().hasClass("group"))
				h.addClass("group");
			if($(this).parent().hasClass("subcat"))
				h.addClass("subcat");
			left.append(h);
		});
		// scroll table to right on first load
		if(!div[0].initialized) {
			div.scroll(TableScroll);
			div.animate({scrollLeft: div.find("table").width()}, 250);
			div[0].initialized = true;
		} else
			// when the contents change it scrolls all the way left, so scroll back to where it was
			setTimeout(function() {
				div.scrollLeft(scroll.left);
			}, 250);
	}, 100);
}

function TableScroll() {
	var s = {top: $(this).scrollTop(), left: $(this).scrollLeft()};
	$(this).find("header.top").css("top", s.top);
	$(this).find("header.left").css("left", s.left);
	$(this).find("header.corner").css(s);
}
