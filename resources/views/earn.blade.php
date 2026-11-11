@extends('layout')

@section('title', 'Earn coins')

@section('content')
    <h1>Earn coins</h1>
    <p class="muted">Complete offers from our partners to earn coins. Most rewards arrive within minutes of the advertiser confirming the offer. A reward can be taken back if the advertiser reverses it.</p>

    @if ($wallUrl === null)
        <form method="post" action="{{ route('earn.age') }}" class="card">
            @csrf
            <p>Offers are only for users who are 16 or older. Users under 18 need a parent's permission.</p>
            <p><label><input type="checkbox" name="age_16_or_older" value="1" required> I am 16 or older</label></p>
            @error('age_16_or_older')<p class="error">Please confirm your age to continue.</p>@enderror
            <button type="submit">Continue</button>
        </form>
    @else
        {{-- Blade escapes the & in the link, which is correct inside an HTML attribute. --}}
        <iframe title="Earn coins" src="{{ $wallUrl }}"
                style="border:0;width:100%;height:calc(100vh - 64px)"></iframe>
        <p class="muted">Follow your offers in My offers at the top of the offerwall. Report a missing reward there.</p>
    @endif

    <section class="card">
        <h2>Recent rewards</h2>
        @if ($recent->isEmpty())
            <p class="muted">No rewards yet.</p>
        @else
            <table>
                <thead><tr><th>Date</th><th>Offer</th><th>Coins</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($recent as $row)
                    <tr>
                        <td>{{ $row->created_at?->toDateString() }}</td>
                        <td>{{ $row->offer_name ?? 'Offer' }}</td>
                        <td>{{ \App\Support\Moonetix::formatMicro($row->reward_micro) }}</td>
                        <td>{{ $row->status === \App\Models\MoonetixTransaction::CREDITED ? 'Credited' : 'Reversed' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
