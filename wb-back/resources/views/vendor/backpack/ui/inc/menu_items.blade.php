{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

<x-backpack::menu-item title="Api keys" icon="la la-key" :link="backpack_url('a-p-i-key')" />
<x-backpack::menu-item title="Cabinets" icon="la la-warehouse" :link="backpack_url('cabinet')" />
<x-backpack::menu-item title="Feedback" icon="la la-comment" :link="backpack_url('feedback')" />
<x-backpack::menu-item title="Settings" icon="la la-cog" :link="backpack_url('setting')" />
<x-backpack::menu-item title="Users" icon="la la-users" :link="backpack_url('user')" />