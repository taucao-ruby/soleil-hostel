{{--
    Example-only Blade snippet for safe review rendering.

    This file is illustrative on purpose:
    - it is not wired to a route
    - it does not depend on a layout
    - it demonstrates @purify usage for Review fields
--}}

@php
    $reviews = $reviews ?? collect();
@endphp

<div class="review-example">
    <h1>Review Rendering Example</h1>
    <p>
        This Blade snippet shows how to render review content safely with <code>@purify</code>.
        It is documentation surface only, not a production UI.
    </p>

    @forelse ($reviews as $review)
        <article class="review-card">
            <h2>@purify($review->title)</h2>

            <div class="review-content">
                @purify($review->content)
            </div>

            <p>
                Reviewed by <strong>@purify($review->guest_name)</strong>
            </p>

            <p>
                Rating: {{ max(1, min(5, (int) $review->rating)) }}/5
            </p>

            @if (! empty($review->created_at))
                <time datetime="{{ optional($review->created_at)->toDateString() }}">
                    {{ optional($review->created_at)->format('M d, Y') }}
                </time>
            @endif
        </article>
    @empty
        <p>No review data was provided to this example view.</p>
    @endforelse

    {{--
        For plain-text output only, use @purifyPlain($review->content).

        Do not render user content with raw Blade output such as:
        {!! $review->content !!}
    --}}
</div>
