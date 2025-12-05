const DragDrop = {
	Data: false,
	Type: false,
	Draggable: {
		created(element, bind) {
			element.draggable = bind.value.disabled ? null : "true";
			element.dragData = bind.value.data;
			element.dataset.dragType = bind.value.type;
			element.dataset.dragName = bind.value.name;
			element.addEventListener("dragstart", event => {
				element.classList.add("dragging");
				DragDrop.Data = element.dragData;
				DragDrop.Type = element.dataset.dragType;
				event.dataTransfer.effectAllowed = "move";
				event.dataTransfer.setData("text/plain", element.dataset.dragName);
			});
			element.addEventListener("dragend", () => {
				element.classList.remove("dragging");
			});
		},
		updated(element, bind) {
			element.draggable = bind.value.disabled ? null : "true";
			element.dragData = bind.value.data;
			element.dataset.dragType = bind.value.type;
			element.dataset.dragName = bind.value.name;
		}
	},
	DropTarget: {
		created(element, bind) {
			let dragLevel = 0;
			element.dropData = bind.value.data;
			element.dataset.dropType = bind.value.type;
			element.addEventListener("dragover", event => {
				event.preventDefault();
				if(element.dropData != DragDrop.Data && element.dataset.dropType == DragDrop.Type && event.dataTransfer)
					event.dataTransfer.dropEffect = "move";
			});
			element.addEventListener("dragenter", () => {
				if(element.dropData != DragDrop.Data && element.dataset.dropType == DragDrop.Type) {
					dragLevel++;
					element.classList.add("droptarget");
				}
			});
			element.addEventListener("dragleave", () => {
				if(element.dropData != DragDrop.Data && element.dataset.dropType == DragDrop.Type && !--dragLevel)
					element.classList.remove("droptarget");
			});
			element.addEventListener("drop", event => {
				if(element.dropData != DragDrop.Data && element.dataset.dropType == DragDrop.Type) {
					dragLevel = 0;
					element.classList.remove("droptarget");
					bind.value.onDrop(DragDrop.Data, element.dropData);
				}
				event.stopPropagation();
				event.preventDefault();
			});
		},
		updated(element, bind) {
			element.dropData = bind.value.data;
			element.dataset.dropType = bind.value.type;
		}
	}
};

export default DragDrop;
