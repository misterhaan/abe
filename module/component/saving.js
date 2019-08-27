import "../../external/jquery-3.4.1.min.js";
import FundApi from "../api/fund.js";
import ReportErrors from "../reportErrors.js";
import FilterAmountKeys from "../filterAmountKeys.js";
import DragDrop from "../dragDrop.js";

export default {
	data() {
		return {
			funds: [],
			editFund: false
		};
	},
	created() {
		FundApi.List().done(funds => {
			this.funds = funds;
		}).fail(this.Error);
		this.$emit("add-action", {
			action: this.Add,
			url: "#saving!add",
			class: "add",
			text: "+",
			tooltip: "Add a new savings fund"
		});
	},
	mixins: [
		ReportErrors,
		FilterAmountKeys,
	],
	methods: {
		IsActive(fund) {
			return fund.balance > 0 || fund.target > 0;
		},
		Add() {
			if(this.CheckSaveOpen()) {
				let index = 0;
				while(index < this.funds.length && (this.funds[index].balance > 0 || this.funds[index].target > 0))
					index++;
				const newFund = {
					id: -1,
					name: "",
					balance: 0,
					balanceDisplay: "0.00",
					target: 0,
					targetDisplay: "0.00"
				};
				this.funds.splice(index, 0, newFund);
				this.editFund = newFund;
				setTimeout(() => $("input.name").focus());
			} else if(this.editFund && this.editFund.id == -1) {
				setTimeout(() => $("input.name").focus());
			} else
				alert("Finish editing " + (this.editFund.name || "(unnamed)") + " before creating a new savings fund.");
		},
		CheckSaveOpen() {
			if(!this.editFund)
				return true;
			if(!this.editFund.name || !this.editFund.balance && !this.editFund.target)
				return false;  // can't save fund with no name or zero balance and target so it has to stay open
			this.Save();
			return true;
		},
		Edit(fund) {
			if(this.editFund != fund && this.CheckSaveOpen()) {
				this.editFund = fund;
				fund.clean = fund.clean || {
					name: fund.name,
					balance: fund.balance,
					target: fund.target
				};
				setTimeout(() => $("input.balance").focus());
			}
		},
		Revert() {
			if(this.editFund) {
				if(this.editFund.id == -1)
					this.funds.splice(this.funds.indexOf(this.editFund), 1);
				else {
					this.editFund.name = this.editFund.clean.name;
					this.editFund.balance = this.editFund.clean.balance;
					this.editFund.target = this.editFund.clean.target;
					delete this.editFund.clean;
				}
				this.editFund = false;
			} else
				this.Error("Attempted to discard changes when nothing was being edited.");
		},
		Save() {
			if(this.editFund) {
				const fund = this.editFund;
				fund.balance = +fund.balance;
				fund.target = +fund.target;
				fund.balanceDisplay = fund.balance.toFixed(2);
				fund.targetDisplay = fund.target.toFixed(2);
				this.editFund = false;
				if(fund.id == -1)
					FundApi.Add(fund.name, fund.balance, fund.target).done(update => {
						fund.id = update.id;
						fund.balanceDisplay = update.balanceDisplay;
						fund.targetDisplay = update.targetDisplay;
					}).fail(error => {
						Edit(fund);
						this.Error(error);
					});
				else
					FundApi.Save(fund.id, fund.name, fund.balance, fund.target).done(update => {
						fund.balanceDisplay = update.balanceDisplay;
						fund.targetDisplay = update.targetDisplay;
					}).fail(error => {
						Edit(fund);
						this.Error(error);
					});
			} else
				this.Error("Attempted to save changes when nothing was being edited.");
		},
		Deactivate() {
			if(this.editFund)
				if(this.editFund.id != -1) {
					const fund = this.editFund;
					this.editFund = false;
					FundApi.Close(fund.id).done(() => {
						const oldIndex = this.funds.indexOf(fund);
						let newIndex = 0;
						while(newIndex < this.funds.length - 1 && this.IsActive(this.funds[newIndex + 1]))
							newIndex++;
						fund.balanceDisplay = (fund.balance = 0).toFixed(2);
						fund.targetDisplay = (fund.target = 0).toFixed(2);
						delete fund.clean;
						if(oldIndex < newIndex)
							this.funds.splice(newIndex, 0, this.funds.splice(oldIndex, 1)[0]);
					}).fail(error => {
						Edit(fund);
						this.Error(error);
					});
				} else
					this.Error("Attempted to close a fund that hasn’t been saved yet.");
			else
				this.Error("Attempted to close a fund when nothing was being edited.")
		},
		MoveUp(fund, index) {
			if(index > 0)
				if(this.IsActive(fund) || !this.IsActive(this.funds[index - 1]))
					FundApi.MoveUp(fund.id).done(success => {
						this.funds[index] = this.funds.splice(index - 1, 1, fund)[0];
					}).fail(this.Error);
				else
					this.Error("Attempted to move inactive fund ahead of an active fund.");
			else
				this.Error("Attempted to move fund up when it is already first.");
		},
		MoveDown(fund, index) {
			if(index < this.funds.length - 1)
				if(!this.IsActive(fund) || this.IsActive(this.funds[index + 1]))
					FundApi.MoveUp(fund.id).done(success => {
						this.funds[index] = this.funds.splice(index + 1, 1, fund)[0];
					}).fail(this.Error);
				else
					this.Error("Attempted to move active fund below an inactive fund.");
			else
				this.Error("Attempted to move fund down when it is already last.");
		},
		MoveFund(movingFund, beforeFund) {
			if(movingFund && beforeFund && movingFund != beforeFund && this.IsActive(movingFund) == this.IsActive(beforeFund)) {
				FundApi.MoveTo(movingFund.id, beforeFund.id).done(() => {
					this.funds.splice(this.funds.indexOf(beforeFund), 0, this.funds.splice(this.funds.indexOf(movingFund), 1)[0]);
				}).fail(this.Error);
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
			<div class=fundview :class="{active: IsActive(fund) || editFund == fund}" v-for="(fund, index) in funds" v-draggable="{data: fund, name: fund.name, type: IsActive(fund) ? 'activeFund' : 'inactiveFund'}" v-droptarget="{data: fund, onDrop: MoveFund, type: IsActive(fund) ? 'activeFund' : 'inactiveFund'}">
				<div class=fund @click="Edit(fund)">
					<h2 v-if="editFund != fund">{{fund.name}}</h2>
					<h2 v-if="editFund == fund">
						<input v-model=fund.name class=name placeholder=name maxlength=32 required>
					</h2>
					<div class=percentfield v-if="IsActive(fund) || editFund == fund">
						<div class=percentvalue :style="{width: Math.max(0, Math.min(100, 100 * fund.balance / fund.target)) + '%'}"></div>
					</div>
					<div v-if="editFund != fund && IsActive(fund)" class=values>{{fund.balanceDisplay}} of {{fund.targetDisplay}}</div>
					<div v-if="editFund == fund" class=values>
						<input class=balance v-model=fund.balance type=number step=.01 placeholder=Current @keypress=FilterAmountKeys>
						of
						<input v-model=fund.target type=number step=.01 placeholder=Target @keypress=FilterAmountKeys>
					</div>
					<div v-if="!IsActive(fund) && editFund != fund" class=values>
						(inactive)
					</div>
				</div>
				<nav v-if="editFund != fund">
					<a class=up title="Move this savings fund higher in the list" href="api/fund/moveUp" @click.prevent="MoveUp(fund, index)" v-if="index && (IsActive(fund) || !IsActive(funds[index - 1]))"><span>▲</span></a>
					<a class=down title="Move this savings fund lower in the list" href="api/fund/moveDown" @click.prevent="MoveDown(fund, index)" v-if="index < funds.length - 1 && (!IsActive(fund) || IsActive(funds[index + 1]))"><span>▼</span></a>
				</nav>
				<nav v-if="editFund == fund">
					<a class=save title="Save changes to this savings fund" href="api/fund/save" @click.prevent=Save><span>save</span></a>
					<a class=undo title="Discard changes" href="#saving!discard" @click.prevent=Revert><span>undo</span></a>
					<a class=delete title="Stop tracking this savings fund" href="api/fund/close" v-if="fund.id != -1 && IsActive(fund)" @click.prevent=Deactivate><span>deactivate</span></a>
				</nav>
			</div>
		</main>
	`
};
