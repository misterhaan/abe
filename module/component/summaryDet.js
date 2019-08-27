export default {
	props: [
		"dates",
		"cats"
	],
	data() {
		return {
			expandedCats: []
		};
	},
	mounted() {
		this.FreezeTableHeaders();
	},
	updated() {
		this.FreezeTableHeaders();
	},
	methods: {
		ToggleCategory(cat) {
			const index = this.expandedCats.indexOf(cat);
			if(index > -1)
				this.expandedCats.splice(index, 1);
			else
				this.expandedCats.push(cat);
		},
		FindParentAmount(date, parentCat) {
			if(parentCat.subcats) {
				let amount = 0;
				for(const subcat of parentCat.subcats)
					if(date.cats[subcat.id])
						amount += +date.cats[subcat.id];
				return amount ? amount.toFixed(2) : "";
			}
			return "";
		},
		FreezeTableHeaders() {
			const div = $("#spendmonthcat > div");
			div.animate({ scrollLeft: 0 }, 250);
			const width = div.find("thead tr td")[0].getBoundingClientRect().width + "px";
			const height = div.find("thead tr td")[0].getBoundingClientRect().height + "px";
			div.find("header").remove();
			const scroll = { top: div.scrollTop(), left: div.scrollLeft() };
			const top = $("<header class=top>")
				.css({ top: scroll.top, left: width, height: height });
			const left = $("<header class=left>")
				.css({ top: height, left: scroll.left, width: width });
			const corner = $("<header class=corner>")
				.css({ top: scroll.top, left: scroll.left, width: width, height: height });
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
				div.animate({ scrollLeft: div.find("table").width() }, 250);
				div[0].initialized = true;
			} else
				// when the contents change it scrolls all the way left, so scroll back to where it was
				setTimeout(function() {
					div.scrollLeft(scroll.left);
				}, 250);
		}
	},
	template: /*html*/ `
		<div id=spendmonthcat><div><table>
			<thead><tr>
				<td></td>
				<th v-for="date in dates">{{date.name}}</th>
			</tr></thead>
			<tbody>
				<tr class=total>
					<th>Total</th>
					<td v-for="date in dates"><a :href="'#transactions!datestart=' + date.start + '/dateend=' + date.end">{{date.net}}</a></td>
				</tr>
				<template v-for="cat in cats">
					<template v-if="cat.subcats">
						<tr class=group :class="{expand: !expandedCats.includes(cat), collapse: expandedCats.includes(cat)}" @click=ToggleCategory(cat)>
							<th>{{cat.name}}</th>
							<td v-for="date in dates">{{FindParentAmount(date, cat)}}</td>
						</tr>
						<template v-if=expandedCats.includes(cat)>
							<tr class=subcat v-for="subcat in cat.subcats">
								<th>{{subcat.name}}</th>
								<td v-for="date in dates"><a :href="'#transactions!cats=' + +subcat.id + '/datestart=' + date.start + '/dateend=' + date.end">{{date.cats[subcat.id]}}</a></td>
							</tr>
						</template>
					</template>
					<tr v-if=!cat.subcats>
						<th>{{cat.name}}</th>
						<td v-for="date in dates"><a :href="'#transactions!cats=' + +cat.id + '/datestart=' + date.start + '/dateend=' + date.end">{{date.cats[cat.id]}}</a></td>
					</tr>
				</template>
			</tbody>
		</table></div></div>
	`
};

function TableScroll() {
	const s = { top: $(this).scrollTop(), left: $(this).scrollLeft() };
	$(this).find("header.top").css("top", s.top);
	$(this).find("header.left").css("left", s.left);
	$(this).find("header.corner").css(s);
}
