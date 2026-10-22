<nav class="tabs" aria-label="{{ $t('إدارة تكاملات المتاجر','Store integration navigation') }}">
    <a href="{{ route('merchant.catalog.index') }}" @if(request()->routeIs('merchant.catalog.index')) aria-current="page" @endif>{{ $t('متاجري والتكاملات','My stores & integrations') }}</a>
    <a href="{{ route('merchant.catalog.platforms') }}" @if(request()->routeIs('merchant.catalog.platforms', 'merchant.catalog.guide')) aria-current="page" @endif>{{ $t('مركز الربط والإضافات','Connectors & plugins') }}</a>
    <a href="{{ route('catalog.index') }}" target="_blank" rel="noopener">{{ $t('عرض الكتالوج العام ↗','View public catalog ↗') }}</a>
</nav>
