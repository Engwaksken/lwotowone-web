@php
    $secretPattern = '/password|remember|token|secret|api_key|fcm|credentials/i';
    $looksEncrypted = fn ($value) => is_string($value) && (str_starts_with($value, 'enc:') || (str_starts_with($value, 'eyJ') && str_contains((string) base64_decode($value, true), '"mac"')));
    $attributesOf = fn ($row) => is_object($row) && method_exists($row, 'getAttributes') ? $row->getAttributes() : (array) $row;
    $moreDetails = fn (array $attributes, array $skip = []) => collect($attributes)
        ->reject(fn ($value, $key) => in_array($key, $skip, true) || preg_match($secretPattern, (string) $key) === 1 || $looksEncrypted($value))
        ->map(fn ($value, $key) => ['label' => \Illuminate\Support\Str::headline((string) $key), 'value' => $value, 'group' => 'More details'])
        ->values()->all();
@endphp
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
@php $enterpriseFilterOptions=collect($data['enterprises'])->pluck('title','id')->prepend('All my enterprises','')->all(); @endphp
<x-filter-bar
    :action="'/portal/enterprise'"
    :search="$earnings['filters']['q']"
    searchName="q"
    searchLabel="Search descriptions"
    searchPlaceholder="Search descriptions"
    :statusOptions="$enterpriseFilterOptions"
    :status="(string) $earnings['filters']['enterprise_id']"
    statusName="enterprise_id"
    statusLabel="Enterprise"
    :periodOptions="['all'=>'All time','week'=>'Current week','month'=>'Current month','year'=>'Current year','custom'=>'Custom date range']"
    :period="$earnings['filters']['period']"
    periodLabel="Period"
    idPrefix="earnings"
>
    @foreach(['start_date'=>'From','end_date'=>'To'] as $field=>$label)
    <div class="field"><label for="earnings-{{ $field }}">{{ $label }}</label><input id="earnings-{{ $field }}" type="date" name="{{ $field }}" value="{{ $earnings['filters'][$field] }}"></div>
    @endforeach
</x-filter-bar>
<p class="muted filter-help">For a custom range, choose Custom date range and enter both dates. Both dates are included.</p>
<div class="stats">
    <div class="stat"><strong>{{ number_format($earnings['income'],2) }}</strong><span>Income · UGX</span></div>
    <div class="stat"><strong>{{ number_format($earnings['expenses'],2) }}</strong><span>Expenses · UGX</span></div>
    <div class="stat"><strong>{{ number_format($earnings['net'],2) }}</strong><span>Net income · UGX</span></div>
</div>
<p class="muted">Totals follow the enterprise and period you pick. Search only filters the history.</p>

<h2>Transaction history</h2>
<div class="panel table-wrap"><table>
    <thead><tr><th scope="col"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Date</th><th scope="col"><i class="fas fa-store" aria-hidden="true"></i> Enterprise</th><th scope="col"><i class="fas fa-align-left" aria-hidden="true"></i> Description</th><th scope="col"><i class="fas fa-exchange-alt" aria-hidden="true"></i> Type</th><th scope="col"><i class="fas fa-coins" aria-hidden="true"></i> Amount (UGX)</th><th scope="col">Actions</th></tr></thead>
    <tbody>
    @forelse($earnings['transactions'] as $transaction)
    @php $transactionEnterprise=collect($data['enterprises'])->firstWhere('id',$transaction->enterprise_id)?->title; $transactionName=$transaction->description?:('Transaction '.$transaction->id); @endphp
    <tr><td>{{ $transaction->occurred_on }}</td><td>{{ $transactionEnterprise }}</td><td>{{ $transaction->description }}</td><td>{{ ucfirst($transaction->type) }}</td><td>{{ number_format($transaction->amount,2) }}</td><td><x-record-view-trigger :dialogId="'view-transaction-'.$transaction->id" :name="$transactionName" /><x-record-view-dialog :id="'view-transaction-'.$transaction->id" :title="$transactionName" :fields="array_merge([['label'=>'Date','value'=>$transaction->occurred_on],['label'=>'Enterprise','value'=>$transactionEnterprise],['label'=>'Description','value'=>$transaction->description],['label'=>'Type','value'=>ucfirst((string) $transaction->type)],['label'=>'Amount (UGX)','value'=>number_format($transaction->amount,2)]], $moreDetails($attributesOf($transaction), ['occurred_on','enterprise_id','description','type','amount']))" /></td></tr>
    @empty
    <tr><td colspan="6">No transactions match these filters.</td></tr>
    @endforelse
    </tbody>
</table>@include('partials.pagination',['rows'=>$earnings['transactions']])</div>
</section>
</div>
@if($errors->any() && old('form_key')==='create')<div data-reopen-dialog="create-enterprise" hidden></div>@elseif($errors->any() && is_string(old('form_key')) && str_starts_with(old('form_key'),'edit-'))<div data-reopen-dialog="edit-enterprise-{{ substr(old('form_key'),5) }}" hidden></div>@elseif($errors->any() && old('form_key')==='transaction')<div data-reopen-dialog="create-transaction" hidden></div>@endif
