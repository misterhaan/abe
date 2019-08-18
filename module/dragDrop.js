import $ from "../external/jquery-3.4.1.min.js";

const DragDrop = {
	Data: false,
	Draggable: {
		bind(el, bind) {
			const element = $(el);
			element.attr("draggable", true);
			element.data("dragData", bind.value.data);
			element.data("dragName", bind.value.name);
			element.on("dragstart", event => {
				element.addClass("dragging");
				DragDrop.Data = element.data("dragData");
				event.originalEvent.dataTransfer.effectAllowed = "move";
				event.originalEvent.dataTransfer.setData("text/plain", element.data("dragName"));
			});
			element.on("dragend", () => {
				element.removeClass("dragging");
			});
		},
		update(el, bind) {
			const element = $(el);
			element.data("dragData", bind.value.data);
			element.data("dragName", bind.value.name);
		}
	},
	DropTarget: {
		bind(el, bind) {
			const element = $(el);
			let dragLevel = 0;
			element.data("dropData", bind.value.data);
			element.on("dragover", event => {
				event.preventDefault();
				event.originalEvent.dataTransfer.dropEffect = "move";
			});
			element.on("dragenter", () => {
				dragLevel++;
				element.addClass("droptarget");
			});
			element.on("dragleave", () => {
				if(!--dragLevel)
					element.removeClass("droptarget");
			});
			element.on("drop", event => {
				dragLevel = 0;
				element.removeClass("droptarget");
				event.stopPropagation();
				event.preventDefault();
				bind.value.onDrop(DragDrop.Data, element.data("dropData"));
			});
		},
		update(el, bind) {
			const element = $(el);
			element.data("dropData", bind.value.data);
		}
	}
};

export default DragDrop;
