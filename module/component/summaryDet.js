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
			const div = document.querySelector("#spendmonthcat > div");
			const width = div.querySelector("thead tr td").getBoundingClientRect().width + "px";
			const height = div.querySelector("thead tr td").getBoundingClientRect().height + "px";
			div.querySelectorAll("header").forEach(h => h.remove());
			const scroll = { top: div.scrollTop, left: div.scrollLeft };
			const top = document.createElement("header");
			top.className = "top";
			Object.assign(top.style, { top: scroll.top, left: width, height: height });
			const left = document.createElement("header");
			left.className = "left";
			Object.assign(left.style, { top: height, left: scroll.left, width: width });
			const corner = document.createElement("header");
			corner.className = "corner";
			Object.assign(corner.style, { top: scroll.top, left: scroll.left, width: width, height: height });
			div.append(top);
			div.append(left);
			div.append(corner);
			div.querySelectorAll("thead th").forEach(th => {
				const h = document.createElement("div");
				h.className = "h";
				h.innerText = th.innerText;
				h.style.width = th.width + "px";
				top.append(h);
			});
			div.querySelectorAll("tbody th").forEach(th => {
				const h = document.createElement("div");
				h.className = "h";
				h.innerText = th.innerText;
				if(th.parentElement.classList.contains("total"))
					h.classList.add("total");
				if(th.parentElement.classList.contains("group"))
					h.classList.add("group");
				if(th.parentElement.classList.contains("subcat"))
					h.classList.add("subcat");
				left.append(h);
			});
			if(!div.initialized) {
				div.addEventListener("scroll", TableScroll);
				div.initialized = true;
			} else
				// when the contents change it scrolls all the way left, so scroll back to where it was
				setTimeout(function() {
					div.scrollTo({ left: scroll.left });
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
	const s = { top: this.scrollTop + "px", left: this.scrollLeft + "px" };
	this.querySelector("header.top").style.top = s.top;
	this.querySelector("header.left").style.left = s.left;
	Object.assign(this.querySelector("header.corner").style, s);
}
