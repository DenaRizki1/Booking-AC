@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" class="brand" style="display: inline-block;">
<span class="brand-mark">{{ mb_strtoupper(mb_substr(trim(strip_tags($slot)), 0, 1)) }}</span>{!! $slot !!}
</a>
</td>
</tr>
