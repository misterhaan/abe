import { nextTick } from "vue";
import FundApi from "../api/fund.js";
import FilterAmountKeys from "../filterAmountKeys.js";
import DragDrop from "../dragDrop.js";

export default {
	data() {
		return {
			funds: [],
			editFund: false
		};
	},
	async created() {
		this.$emit("add-action", {
			action: this.Add,
			url: "#saving!add",
			class: "add",
			text: "+",
			tooltip: "Add a new savings fund"
		});
		this.funds = await FundApi.List();
		if(!this.funds.length)
			this.Add();
	},
	mixins: [FilterAmountKeys],
	methods: {
		IsActive(fund) {
			return fund.Balance > 0 || fund.Target > 0;
		},
		async Add() {
			if(this.CheckSaveOpen()) {
				let index = 0;
				while(index < this.funds.length && (this.funds[index].Balance > 0 || this.funds[index].Target > 0))
					index++;
				const newFund = {
					ID: -1,
					Name: "",
					Balance: 0,
					BalanceDisplay: "0.00",
					Target: 0,
					TargetDisplay: "0.00"
				};
				this.funds.splice(index, 0, newFund);
				this.editFund = newFund;
				await nextTick();
				document.querySelector("input.name").focus();
			} else if(this.editFund && this.editFund.ID == -1) {
				await nextTick();
				document.querySelector("input.name").focus();
			} else
				alert("Finish editing " + (this.editFund.Name || "(unnamed)") + " before creating a new savings fund.");
		},
		CheckSaveOpen() {
			if(!this.editFund)
				return true;
			if(!this.editFund.Name || !this.editFund.Balance && !this.editFund.Target)
				return false;  // can't save fund with no name or zero balance and target so it has to stay open
			this.Save();
			return true;
		},
		async Edit(fund) {
			if(this.editFund != fund && this.CheckSaveOpen()) {
				this.editFund = fund;
				fund.clean = fund.clean || {
					Name: fund.Name,
					Balance: fund.Balance,
					Target: fund.Target
				};
				await nextTick();
				document.querySelector("input.balance").focus();
			}
		},
		Revert() {
			if(this.editFund) {
				if(this.editFund.ID == -1)
					this.funds.splice(this.funds.indexOf(this.editFund), 1);
				else {
					this.editFund.Name = this.editFund.clean.Name;
					this.editFund.Balance = this.editFund.clean.Balance;
					this.editFund.Target = this.editFund.clean.Target;
					delete this.editFund.clean;
				}
				this.editFund = false;
			} else
				throw new Error("Attempted to discard changes when nothing was being edited.");
		},
		async Save() {
			if(this.editFund) {
				const fund = this.editFund;
				fund.Balance = +fund.Balance;
				fund.Target = +fund.Target;
				fund.BalanceDisplay = fund.Balance.toFixed(2);
				fund.TargetDisplay = fund.Target.toFixed(2);
				this.editFund = false;
				if(fund.ID == -1)
					try {
						const update = await FundApi.Add(fund.Name, fund.Balance, fund.Target);
						fund.ID = update.ID;
						fund.BalanceDisplay = update.BalanceDisplay;
						fund.TargetDisplay = update.TargetDisplay;
					} catch {
						Edit(fund);
					}
				else
					try {
						const update = await FundApi.Save(fund.ID, fund.Name, fund.Balance, fund.Target);
						fund.BalanceDisplay = update.BalanceDisplay;
						fund.TargetDisplay = update.TargetDisplay;
					} catch {
						this.Edit(fund);
					}
			} else
				throw new Error("Attempted to save changes when nothing was being edited.");
		},
		async Deactivate() {
			if(this.editFund)
				if(this.editFund.ID != -1) {
					const fund = this.editFund;
					this.editFund = false;
					try {
						await FundApi.Close(fund.ID);
						const oldIndex = this.funds.indexOf(fund);
						let newIndex = 0;
						while(newIndex < this.funds.length - 1 && this.IsActive(this.funds[newIndex + 1]))
							newIndex++;
						fund.BalanceDisplay = (fund.Balance = 0).toFixed(2);
						fund.TargetDisplay = (fund.Target = 0).toFixed(2);
						delete fund.clean;
						if(oldIndex < newIndex)
							this.funds.splice(newIndex, 0, this.funds.splice(oldIndex, 1)[0]);
					} catch {
						Edit(fund);
					}
				} else
					throw new Error("Attempted to close a fund that hasn’t been saved yet.");
			else
				throw new Error("Attempted to close a fund when nothing was being edited.");
		},
		async MoveUp(fund, index) {
			if(index > 0)
				if(this.IsActive(fund) || !this.IsActive(this.funds[index - 1])) {
					await FundApi.MoveUp(fund.ID);
					this.funds[index] = this.funds.splice(index - 1, 1, fund)[0];
				} else
					throw new Error("Attempted to move inactive fund ahead of an active fund.");
			else
				throw new Error("Attempted to move fund up when it is already first.");
		},
		async MoveDown(fund, index) {
			if(index < this.funds.length - 1)
				if(!this.IsActive(fund) || this.IsActive(this.funds[index + 1])) {
					await FundApi.MoveDown(fund.ID);
					this.funds[index] = this.funds.splice(index + 1, 1, fund)[0];
				} else
					throw new Error("Attempted to move active fund below an inactive fund.");
			else
				throw new Error("Attempted to move fund down when it is already last.");
		},
		async MoveFund(movingFund, beforeFund) {
			if(movingFund && beforeFund && movingFund != beforeFund && this.IsActive(movingFund) == this.IsActive(beforeFund)) {
				await FundApi.MoveTo(movingFund.ID, beforeFund.ID);
				this.funds.splice(this.funds.indexOf(beforeFund), 0, this.funds.splice(this.funds.indexOf(movingFund), 1)[0]);
			}
		}
	},
	directives: {
		draggable: DragDrop.Draggable,
		droptarget: DragDrop.DropTarget
	},
	// TODO:  show savings allocation donut
	template: /*html*/ `
		<main role=main>
			<div class=fundview :class="{active: IsActive(fund) || editFund == fund}" v-for="(fund, index) in funds" v-draggable="{disabled: editFund, data: fund, name: fund.Name, type: IsActive(fund) ? 'activeFund' : 'inactiveFund'}" v-droptarget="{data: fund, onDrop: MoveFund, type: IsActive(fund) ? 'activeFund' : 'inactiveFund'}">
				<div class=fund @click="Edit(fund)">
					<h2 v-if="editFund != fund">{{fund.Name}}</h2>
					<h2 v-if="editFund == fund">
						<input v-model.trim=fund.Name class=name placeholder=name maxlength=32 required @click.stop>
					</h2>
					<div class=percentfield v-if="IsActive(fund) || editFund == fund">
						<div class=percentvalue :style="{width: Math.max(0, Math.min(100, 100 * fund.Balance / fund.Target)) + '%'}"></div>
					</div>
					<div v-if="editFund != fund && IsActive(fund)" class=values>{{fund.BalanceDisplay}} of {{fund.TargetDisplay}}</div>
					<div v-if="editFund == fund" class=values>
						<input class=balance v-model=fund.Balance type=number step=.01 placeholder=Current @click.stop @keypress=FilterAmountKeys>
						of
						<input v-model=fund.Target type=number step=.01 placeholder=Target @click.stop @keypress=FilterAmountKeys>
					</div>
					<div v-if="!IsActive(fund) && editFund != fund" class=values>
						(inactive)
					</div>
				</div>
				<nav v-if="editFund != fund">
					<a class=up title="Move this savings fund higher in the list" href="api/fund/moveUp" @click.prevent.stop="MoveUp(fund, index)" v-if="index && (IsActive(fund) || !IsActive(funds[index - 1]))"><span>▲</span></a>
					<a class=down title="Move this savings fund lower in the list" href="api/fund/moveDown" @click.prevent.stop="MoveDown(fund, index)" v-if="index < funds.length - 1 && (!IsActive(fund) || IsActive(funds[index + 1]))"><span>▼</span></a>
				</nav>
				<nav v-if="editFund == fund">
					<a class=save title="Save changes to this savings fund" href="api/fund/save" @click.prevent.stop=Save><span>save</span></a>
					<a class=undo title="Discard changes" href="#saving!discard" @click.prevent.stop=Revert><span>undo</span></a>
					<a class=delete title="Stop tracking this savings fund" href="api/fund/close" v-if="fund.ID != -1 && IsActive(fund)" @click.prevent.stop=Deactivate><span>deactivate</span></a>
				</nav>
			</div>
		</main>
	`
};
