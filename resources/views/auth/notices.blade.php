@if(session('success'))<div class="notice success auth-notice" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice error auth-notice" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
