<p class="page-intro">Develop your idea and track income and expenses in UGX. You enter these records yourself.</p>

<div class="toolbar"><span></span><button type="button" data-dialog-open="create-enterprise">Create an enterprise</button></div>
<div class="tabs" data-tabs>
<div class="tab-list" role="tablist" aria-label="Enterprise and earnings">
    <button type="button" role="tab" id="tab-enterprises" aria-controls="panel-enterprises" aria-selected="true">My enterprises <span class="tab-count">{{ count($data['enterprises']) }}</span></button>
    <button type="button" role="tab" id="tab-earnings" aria-controls="panel-earnings" aria-selected="false" tabindex="-1">Earnings and history</button>
</div>
<section class="tab-panel" role="tabpanel" id="panel-enterprises" aria-labelledby="tab-enterprises">
<h2>My enterprises</h2>
@forelse($data['enterprises'] as $enterprise)
<article class="panel enterprise-card"><div><span class="tag">{{ ucfirst($enterprise->stage) }}</span><h3>{{ $enterprise->title }}</h3><p>{{ $enterprise->sector }} · {{ $enterprise->idea }}</p></div><button type="button" class="secondary" data-dialog-open="edit-enterprise-{{ $enterprise->id }}">Edit details</button></article>
<dialog class="form-dialog" id="edit-enterprise-{{ $enterprise->id }}" aria-labelledby="edit-enterprise-title-{{ $enterprise->id }}">
    <div class="dialog-heading"><h2 id="edit-enterprise-title-{{ $enterprise->id }}">Edit enterprise</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
    <form class="dialog-form" method="post" action="/actions/update-enterprise">@csrf
        <input type="hidden" name="enterprise_id" value="{{ $enterprise->id }}">
        @include('portal.enterprise-fields',['enterpriseRecord'=>$enterprise,'formKey'=>'edit-'.$enterprise->id])
        <div class="dialog-actions"><button>Save enterprise changes</button></div>
    </form>
</dialog>
@empty
<div class="empty">You have no enterprises yet. Create one to get started.</div>
@endforelse

<dialog class="form-dialog" id="create-enterprise" aria-labelledby="create-enterprise-title">
    <div class="dialog-heading"><h2 id="create-enterprise-title">Create an enterprise</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
    <form class="dialog-form" method="post" action="/actions/enterprise">@csrf
        @include('portal.enterprise-fields',['enterpriseRecord'=>null,'formKey'=>'create'])
        <div class="dialog-actions"><button>Create enterprise</button></div>
    </form>
</dialog>

@if(count($data['enterprises']))
<div class="toolbar"><span></span><button type="button" class="secondary" data-dialog-open="create-transaction">Record income or expense</button></div>
<dialog class="form-dialog" id="create-transaction" aria-labelledby="create-transaction-title">
<div class="dialog-heading"><h2 id="create-transaction-title">Record income or expense</h2><button class="secondary small" type="button" data-dialog-close>Close</button></div>
<form class="dialog-form" method="post" action="/actions/income">
    @csrf
    <input type="hidden" name="form_key" value="transaction">
    <div class="field"><label for="transaction-enterprise">Enterprise</label>
        <select id="transaction-enterprise" name="enterprise_id" required>
            @foreach($data['enterprises'] as $enterprise)
            <option value="{{ $enterprise->id }}" @selected(old('form_key')==='transaction' && (string)old('enterprise_id')===(string)$enterprise->id)>{{ $enterprise->title }}</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label for="transaction-type">Type</label><select id="transaction-type" name="type" required>
        <option value="income">Income</option><option value="expense" @selected(old('form_key')==='transaction' && old('type')==='expense')>Expense</option>
    </select></div>
    @foreach(['amount'=>'Amount (UGX)','description'=>'Description','occurred_on'=>'Date'] as $field=>$label)
    <div class="field"><label for="transaction-{{ $field }}">{{ $label }}</label>
        <input id="transaction-{{ $field }}" name="{{ $field }}" type="{{ $field==='amount'?'number':($field==='occurred_on'?'date':'text') }}"
            value="{{ old('form_key')==='transaction'?old($field):($field==='occurred_on'?today()->toDateString():'') }}"
            @if($field==='amount') min="0.01" max="9999999999.99" step="0.01" @endif
            @if($field==='description') maxlength="255" @endif
            @if($field==='occurred_on') max="{{ today()->toDateString() }}" @endif required>
    </div>
    @endforeach
    <div class="dialog-actions"><button>Save transaction</button></div>
</form></dialog>
@endif
</section>

<section class="tab-panel" role="tabpanel" id="panel-earnings" aria-labelledby="tab-earnings" hidden>
<h2>Earnings summary</h2>
<form class="panel filter-form" method="get" action="/portal/enterprise">
    <div class="field"><label for="earnings-enterprise">Enterprise</label><select id="earnings-enterprise" name="enterprise_id">
        <option value="">All my enterprises</option>
        @foreach($data['enterprises'] as $enterprise)
        <option value="{{ $enterprise->id }}" @selected((string)$earnings['filters']['enterprise_id']===(string)$enterprise->id)>{{ $enterprise->title }}</option>
        @endforeach
    </select></div>
    <div class="field"><label for="earnings-period">Period</label><select id="earnings-period" name="period">
        @foreach(['all'=>'All time','week'=>'Current week','month'=>'Current month','year'=>'Current year','custom'=>'Custom date range'] as $value=>$label)
        <option value="{{ $value }}" @selected($earnings['filters']['period']===$value)>{{ $label }}</option>
        @endforeach
    </select></div>
    <p class="muted filter-help">For a custom range, choose Custom date range and enter both dates. Both dates are included.</p>
    @foreach(['start_date'=>'From','end_date'=>'To'] as $field=>$label)
    <div class="field"><label for="earnings-{{ $field }}">{{ $label }}</label><input id="earnings-{{ $field }}" type="date" name="{{ $field }}" value="{{ $earnings['filters'][$field] }}"></div>
    @endforeach
    <div class="field"><label for="earnings-search">Search descriptions</label><input id="earnings-search" name="q" maxlength="255" value="{{ $earnings['filters']['q'] }}"></div>
    <div class="filter-actions"><button><i class="fas fa-search" aria-hidden="true"></i> Apply filters</button> <a href="/portal/enterprise">Reset</a></div>
</form>
<div class="stats">
    <div class="stat"><strong>{{ number_format($earnings['income'],2) }}</strong><span>Income · UGX</span></div>
    <div class="stat"><strong>{{ number_format($earnings['expenses'],2) }}</strong><span>Expenses · UGX</span></div>
    <div class="stat"><strong>{{ number_format($earnings['net'],2) }}</strong><span>Net income · UGX</span></div>
</div>
<p class="muted">Totals follow the enterprise and period you pick. Search only filters the history.</p>

<h2>Transaction history</h2>
<div class="panel table-wrap"><table>
    <thead><tr><th scope="col"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Date</th><th scope="col"><i class="fas fa-store" aria-hidden="true"></i> Enterprise</th><th scope="col"><i class="fas fa-align-left" aria-hidden="true"></i> Description</th><th scope="col"><i class="fas fa-exchange-alt" aria-hidden="true"></i> Type</th><th scope="col"><i class="fas fa-coins" aria-hidden="true"></i> Amount (UGX)</th></tr></thead>
    <tbody>
    @forelse($earnings['transactions'] as $transaction)
    <tr><td>{{ $transaction->occurred_on }}</td><td>{{ collect($data['enterprises'])->firstWhere('id',$transaction->enterprise_id)?->title }}</td><td>{{ $transaction->description }}</td><td>{{ ucfirst($transaction->type) }}</td><td>{{ number_format($transaction->amount,2) }}</td></tr>
    @empty
    <tr><td colspan="5">No transactions match these filters.</td></tr>
    @endforelse
    </tbody>
</table>@include('partials.pagination',['rows'=>$earnings['transactions']])</div>
</section>
</div>
@if($errors->any() && old('form_key')==='create')<div data-reopen-dialog="create-enterprise" hidden></div>@elseif($errors->any() && is_string(old('form_key')) && str_starts_with(old('form_key'),'edit-'))<div data-reopen-dialog="edit-enterprise-{{ substr(old('form_key'),5) }}" hidden></div>@elseif($errors->any() && old('form_key')==='transaction')<div data-reopen-dialog="create-transaction" hidden></div>@endif
