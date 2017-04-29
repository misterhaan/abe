$(function() {
	ko.applyBindings(MonthsVM = new MonthsViewModel());
});

function MonthsViewModel() {
	var self = this;
	this.cats = ko.observableArray([]);
	this.months = ko.observableArray([]);

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
