@extends('layouts.app')
@section('title', 'FAQ and service policies')
@section('content')
<div class="content">
    <div class="eyebrow">Before you host</div>
    <h1>FAQ and service policies</h1>
    <p class="muted">Student Hosting is intentionally simple hosting for learning, demonstrations, experiments, and temporary websites.</p>

    <div class="notice"><strong>Important:</strong> Do not use this service as the only home for your files or database. We provide no backups, recovery guarantee, or availability guarantee. Keep your own complete copy elsewhere.</div>

    <section class="card" style="margin-bottom:24px">
        <h2>Service policy</h2>
        <h3>Suitable use</h3>
        <p>Use this service for coursework, prototypes, tests, demonstrations, and temporary low-risk websites. It is not suitable for business-critical production systems, permanent archives, irreplaceable work, confidential records, financial information, health information, or other sensitive personal data.</p>

        <h3>No backups or recovery promise</h3>
        <p>We do not create or maintain customer backups. Files, databases, deployments, containers, or the entire service may be lost because of hardware failure, software faults, mistakes, security incidents, account expiry, or other events. We may be unable to recover anything. You are solely responsible for keeping current offline or external copies.</p>

        <h3>Limited access model</h3>
        <p>Customers manage websites only through the control panel. There is no SSH, shell, SFTP/FTP, direct server access, direct container access, or database administration interface. The operator does not provide a routine file-retrieval or recovery service. Authorized operators may still need technical access to infrastructure, logs, and stored content for maintenance, security, abuse handling, or legal compliance.</p>

        <h3>No service-level guarantee</h3>
        <p>Hosting may be interrupted, changed, suspended, or unavailable. There is no uptime, performance, response-time, support, or data-durability guarantee. Resource limits are enforced and websites may be suspended when access expires or quotas are exceeded.</p>

        <h3>Customer responsibility</h3>
        <p>You must own or have permission to host everything you upload. Do not use the service for unlawful, harmful, abusive, deceptive, infringing, malicious, or resource-intensive content. You are responsible for your website, its code, its data, and maintaining your own source copy.</p>

        <h3>No feature roadmap promise</h3>
        <p>Features not available today should be treated as unavailable. Suggestions are welcome, but submission does not mean a feature is planned, accepted, scheduled, or guaranteed.</p>
    </section>

    <section class="card">
        <h2>Frequently asked questions</h2>
        <details open><summary>What should I host here?</summary><p>Small static or PHP websites used for learning, assignments, prototypes, testing, or short-lived demonstrations.</p></details>
        <details><summary>Is this suitable for a production business website?</summary><p>No. Use a production hosting provider with contractual uptime, backups, recovery procedures, and support.</p></details>
        <details><summary>Are my website and database backed up?</summary><p>No. Always keep your source files, uploads, and database exports somewhere else.</p></details>
        <details><summary>Can you recover deleted or lost data?</summary><p>Recovery is not offered or guaranteed. Even if some infrastructure data happens to remain, it should not be treated as a backup.</p></details>
        <details><summary>Can I access the server using SSH, FTP, SFTP, or a database tool?</summary><p>No. Customer access is limited to the features exposed in the control panel.</p></details>
        <details><summary>What happens when prepaid access expires?</summary><p>Website management and deployments stop, and websites may be suspended. Renew before expiry and keep your own data copy regardless.</p></details>
        <details><summary>Will a requested feature be added?</summary><p>There is currently no promised future roadmap. Feedback helps us understand demand but creates no commitment.</p></details>
        <details><summary>How can I send feedback?</summary><p>@auth Use the <a href="{{ route('feedback.index') }}">feedback form</a>. @else Sign in, then open Feedback from the navigation. @endauth</p></details>
    </section>
</div>
@endsection
