import "../external/jquery-3.4.1.min.js";

const DragDrop = {
	Data: false,
	Type: false,
	Draggable: {
		bind(el, bind) {
			const element = $(el);
			element.attr("draggable", true);
			element.data("dragData", bind.value.data);
			element.data("dragType", bind.value.type);
			element.data("dragName", bind.value.name);
			element.on("dragstart", event => {
				element.addClass("dragging");
				DragDrop.Data = element.data("dragData");
				DragDrop.Type = element.data("dragType");
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
			element.data("dragType", bind.value.type);
			element.data("dragName", bind.value.name);
		}
	},
	DropTarget: {
		bind(el, bind) {
			const element = $(el);
			let dragLevel = 0;
			element.data("dropData", bind.value.data);
			element.data("dropType", bind.value.type);
			element.on("dragover", event => {
				event.preventDefault();
				if(element.data("dropData") != DragDrop.Data && element.data("dropType") == DragDrop.Type)
					event.originalEvent.dataTransfer.dropEffect = "move";
			});
			element.on("dragenter", () => {
				if(element.data("dropData") != DragDrop.Data && element.data("dropType") == DragDrop.Type) {
					dragLevel++;
					element.addClass("droptarget");
				}
			});
			element.on("dragleave", () => {
				if(element.data("dropData") != DragDrop.Data && element.data("dropType") == DragDrop.Type && !--dragLevel)
					element.removeClass("droptarget");
			});
			element.on("drop", event => {
				if(element.data("dropData") != DragDrop.Data && element.data("dropType") == DragDrop.Type) {
					dragLevel = 0;
					element.removeClass("droptarget");
					bind.value.onDrop(DragDrop.Data, element.data("dropData"));
				}
				event.stopPropagation();
				event.preventDefault();
			});
		},
		update(el, bind) {
			$(el).data("dropData", bind.value.data);
			$(el).data("dropType", bind.value.type);
		}
	}
};

export default DragDrop;
