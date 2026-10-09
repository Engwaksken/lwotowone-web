@extends('layout')
@section('title', $page==='faq'?'Frequently asked questions | Lwotowone':'Your Lwotowone user guide')
@section('content')
<section class="help-hero">
    <span class="eyebrow"><i class="fas {{ $page==='faq'?'fa-circle-question':'fa-compass' }}" aria-hidden="true"></i> Here to help</span>
    <h1>{{ $page==='faq'?'Good questions deserve clear answers.':'Your guide to getting started.' }}</h1>
    <p>{{ $page==='faq'?'Find answers about learning, opportunities and getting support. If you are unsure, you are always welcome to contact our team.':'A friendly step-by-step introduction to finding learning, building your profile and getting support on Lwotowone.' }}</p>
    <div class="actions"><a class="button" href="{{ $page==='faq'?'/user-guide':'/faq' }}">{{ $page==='faq'?'Read the user guide':'Browse FAQs' }}</a><a class="button secondary" href="/contact">Talk to our team</a></div>
</section>

@if($page==='faq')
<section class="help-section" aria-labelledby="faq-heading">
    <div class="help-section-heading"><span class="eyebrow">Frequently asked questions</span><h2 id="faq-heading">Let’s make your next step easier.</h2><p>Choose a question to see a practical answer.</p></div>
    <div class="faq-list">
        @foreach([
            ['Who can join Lwotowone?','Young people and community members interested in practical learning, skills development, mentorship or enterprise support can explore our programmes. Each opportunity may have its own eligibility and application details.'],
            ['How do I find a course or programme?','Visit Programmes or Learn from the main menu. Open an item to read its description and requirements. Create an account to access learner features and track your learning.'],
            ['Do I need to pay to take part?','Fees and support vary by programme. Check the details on the programme or course page, or contact our team before making a payment. Never send money to an individual claiming to represent us without confirming through our official contact channels.'],
            ['Can I use the platform if I have a disability?','Yes. We aim to make learning welcoming and accessible. You can adjust text size, enable high contrast and reduce motion using Accessibility options. If you need a particular accommodation or an accessible format, contact us and tell us what would help.'],
            ['How do I get help with my account?','Use the User guide for sign-in and profile steps. If you cannot access your account, use the password reset link on the sign-in page or contact our team for support.'],
            ['How do I apply for an opportunity?','Open the opportunity to review its organisation, location, deadline and requirements. Sign in if an application option is provided, and submit your information before the listed deadline.'],
            ['Can I talk to a real person?','Yes. Use Contact us to send an enquiry. Our on-page help assistant can also point you to common information, but it is an automated guide and does not replace support from our team.'],
        ] as [$question,$answer])
            <details class="faq-item"><summary>{{ $question }}</summary><p>{{ $answer }}</p></details>
        @endforeach
    </div>
</section>
@else
<section class="guide-grid" aria-label="How to use Lwotowone">
    @foreach([
        ['01','Explore','Browse programmes, courses, events, stories and current opportunities. Use search to find topics that matter to you.'],
        ['02','Create your account','Choose Join us, provide your details and keep your sign-in information safe. You can return to your learning from any device.'],
        ['03','Set up your profile','Add the information requested so our team can understand your interests and connect you with relevant learning and support.'],
        ['04','Learn and practise','Open My learning to continue a course. Work through lessons and practical activities at your own pace, and ask your instructor when you need help.'],
        ['05','Find your next opportunity','Visit Grow and connect in your account for mentorship, events, enterprise resources and opportunities. Check requirements and dates carefully.'],
        ['06','Make the platform work for you','Open Accessibility options in the page header to increase text size, use high contrast or reduce motion. These preferences are saved on your device.'],
    ] as [$number,$title,$description])
        <article class="guide-card"><span class="guide-number">{{ $number }}</span><div><h2>{{ $title }}</h2><p>{{ $description }}</p></div></article>
    @endforeach
</section>
<section class="help-contact"><div><span class="eyebrow">You do not have to figure it out alone</span><h2>Need a hand along the way?</h2><p>Contact us and tell us what you are trying to do. We will help you find the right next step.</p></div><a class="button" href="/contact">Contact our team</a></section>
@endif
@endsection
