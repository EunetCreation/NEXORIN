<section id="material-evidence" class="nxmc-panel nxmc-reveal" data-nxmc-tab-panel>
    <header class="nxmc-panel-head">
        <div>
            <span class="nxmc-section-kicker">Professional evidence pack</span>
            <h3>Documents & compliance center</h3>
            <p>Govern certificate validity, warranties, manuals, compatibility records, drawings, and service evidence independently from the parsed technical datasheet.</p>
        </div>
        <span class="nxmc-panel-badge {{ $documentPackReady && $certificateReady ? 'is-green' : 'is-amber' }}">
            {{ $documentPackReady && $certificateReady ? 'Evidence pack verified' : 'Verification required' }}
        </span>
    </header>

    <div class="nxmc-panel-body">
        <div class="nxmc-evidence-overview">
            <div class="nxmc-evidence-profile">
                <span class="nxmc-panel-kicker">Category evidence blueprint</span>
                <h4>{{ $catalogProfile['name'] ?? 'General Material' }}</h4>
                <p>{{ $catalogProfile['description'] ?? 'Document requirements are governed manually for this material.' }}</p>
                <div class="nxmc-evidence-requirement-row">
                    @forelse (($catalogProfile['certificates'] ?? []) as $requirement)
                        <span class="nxmc-requirement-chip is-certificate">{{ $requirement }}</span>
                    @empty
                        <span class="nxmc-requirement-chip">No mandatory certificate profile</span>
                    @endforelse
                    @foreach (($catalogProfile['documents'] ?? []) as $requirement)
                        <span class="nxmc-requirement-chip is-document">{{ $documentTypes[$requirement] ?? str($requirement)->replace('_', ' ')->title() }}</span>
                    @endforeach
                </div>
            </div>

            <div class="nxmc-evidence-kpis" aria-label="Evidence pack metrics">
                <div><span>Certificates</span><strong>{{ number_format($activeCertificates->count()) }}</strong><small>{{ $certificateReady ? 'Required coverage verified' : number_format($missingCertificates->count()).' unresolved' }}</small></div>
                <div><span>Documents</span><strong>{{ number_format($activeDocuments->count()) }}</strong><small>{{ $documentPackReady ? 'Required pack verified' : number_format($missingDocuments->count()).' unresolved' }}</small></div>
                <div><span>Expired</span><strong>{{ number_format($expiredEvidenceCount) }}</strong><small>{{ $expiredEvidenceCount > 0 ? 'Renewal required' : 'No expired active evidence' }}</small></div>
            </div>
        </div>

        @if ($canModify && auth()->user()->hasCompanyPermission($company, 'equipment.update'))
            <div class="nxmc-intake-grid">
                <details class="nxmc-evidence-disclosure" @if (old('certificate_type') || old('certificate_title') || old('certificate_number')) open @endif>
                    <summary><span><strong>Register compliance evidence</strong><small>Certificate, issuer, validity, and source</small></span><b>Open intake</b></summary>
                <form method="POST" action="{{ route('companies.materials.certificates.store', [$company, $material->id]) }}" enctype="multipart/form-data" class="nxmc-evidence-form" data-nxmc-action="certificate">
                    @csrf
                    <div class="nxmc-evidence-form-head">
                        <span class="nxmc-evidence-form-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-4"/></svg></span>
                        <div><span>Certificate intake</span><strong>Register compliance evidence</strong><p>Capture standard, issuer, certificate number, validity, and original evidence source.</p></div>
                    </div>
                    <div class="nxmc-compact-form-grid">
                        <div class="nxmc-field"><label class="nxmc-label" for="certificate_type">Certificate type</label><input id="certificate_type" class="nxmc-input" name="certificate_type" placeholder="IEC 61215" required></div>
                        <div class="nxmc-field"><label class="nxmc-label" for="certificate_title">Title</label><input id="certificate_title" class="nxmc-input" name="title" placeholder="Product certificate" required></div>
                        <div class="nxmc-field"><label class="nxmc-label" for="certificate_number">Certificate number</label><input id="certificate_number" class="nxmc-input" name="certificate_number"></div>
                        <div class="nxmc-field"><label class="nxmc-label" for="certificate_issuer">Issuer</label><input id="certificate_issuer" class="nxmc-input" name="issuer" placeholder="TÜV / UL / Intertek"></div>
                        <div class="nxmc-field"><label class="nxmc-label" for="certificate_issued_at">Issued</label><input id="certificate_issued_at" class="nxmc-input" type="date" name="issued_at"></div>
                        <div class="nxmc-field"><label class="nxmc-label" for="certificate_expires_at">Expires</label><input id="certificate_expires_at" class="nxmc-input" type="date" name="expires_at"></div>
                        <div class="nxmc-field nxmc-form-span-2"><label class="nxmc-label" for="certificate_file">Certificate file</label><input id="certificate_file" class="nxmc-file" type="file" name="certificate_file" accept=".pdf,.jpg,.jpeg,.png,.webp"><span class="nxmc-field-hint">PDF or image, maximum 15 MB. A source URL may be used instead.</span></div>
                        <div class="nxmc-field nxmc-form-span-2"><label class="nxmc-label" for="certificate_url">External source URL</label><input id="certificate_url" class="nxmc-input" type="url" name="external_url" placeholder="https://"></div>
                    </div>
                    <button class="nxmc-button nxmc-button-primary" type="submit">Register certificate</button>
                </form>
                </details>

                <details class="nxmc-evidence-disclosure is-violet" @if (old('document_type') || old('document_title') || old('document_revision')) open @endif>
                    <summary><span><strong>Register supporting document</strong><small>Warranty, manual, protocol, drawing, or service evidence</small></span><b>Open intake</b></summary>
                <form method="POST" action="{{ route('companies.materials.documents.store', [$company, $material->id]) }}" enctype="multipart/form-data" class="nxmc-evidence-form is-violet" data-nxmc-action="document">
                    @csrf
                    <div class="nxmc-evidence-form-head">
                        <span class="nxmc-evidence-form-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 11h6M9 15h6"/></svg></span>
                        <div><span>Document intake</span><strong>Build the professional evidence pack</strong><p>Register warranty, manual, protocol, drawing, quote, checklist, or service documentation.</p></div>
                    </div>
                    <div class="nxmc-compact-form-grid">
                        <div class="nxmc-field"><label class="nxmc-label" for="document_type">Document type</label><select id="document_type" class="nxmc-select" name="document_type" required>@foreach ($documentTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="nxmc-field"><label class="nxmc-label" for="document_title">Title</label><input id="document_title" class="nxmc-input" name="title" required></div>
                        <div class="nxmc-field"><label class="nxmc-label" for="document_revision">Revision</label><input id="document_revision" class="nxmc-input" name="revision" placeholder="Rev. A"></div>
                        <div class="nxmc-field"><label class="nxmc-label" for="document_issued_at">Issued</label><input id="document_issued_at" class="nxmc-input" type="date" name="issued_at"></div>
                        <div class="nxmc-field"><label class="nxmc-label" for="document_expires_at">Expires</label><input id="document_expires_at" class="nxmc-input" type="date" name="expires_at"></div>
                        <div class="nxmc-field"><label class="nxmc-label" for="document_notes">Evidence note</label><input id="document_notes" class="nxmc-input" name="notes" placeholder="Scope, limitation, or review note"></div>
                        <div class="nxmc-field nxmc-form-span-2"><label class="nxmc-label" for="document_file">Document file</label><input id="document_file" class="nxmc-file" type="file" name="document_file" accept=".pdf,.txt,.csv,.xls,.xlsx,.doc,.docx,.jpg,.jpeg,.png,.webp"><span class="nxmc-field-hint">Maximum 20 MB. A source URL may be used instead.</span></div>
                        <div class="nxmc-field nxmc-form-span-2"><label class="nxmc-label" for="document_url">External source URL</label><input id="document_url" class="nxmc-input" type="url" name="external_url" placeholder="https://"></div>
                    </div>
                    <button class="nxmc-button nxmc-button-primary" type="submit">Register document</button>
                </form>
                </details>
            </div>
        @endif

        <div class="nxmc-evidence-columns">
            <div>
                <div class="nxmc-evidence-list-head"><div><span class="nxmc-panel-kicker">Certificate register</span><h4>Compliance certificates</h4></div><span>{{ number_format($activeCertificates->count()) }} active</span></div>
                <div class="nxmc-evidence-register">
                    @forelse ($activeCertificates as $certificate)
                        @php
                            $isExpired = $certificate->expires_at?->isPast() ?? false;
                            $status = $isExpired ? 'expired' : ($certificate->verification_status ?? 'pending');
                        @endphp
                        <article class="nxmc-evidence-card {{ $status === 'verified' ? 'is-verified' : ($status === 'expired' || $status === 'rejected' ? 'is-alert' : '') }}">
                            <div class="nxmc-evidence-card-main">
                                <span class="nxmc-evidence-type">{{ $certificate->certificate_type }}</span>
                                <strong>{{ $certificate->title }}</strong>
                                <p>{{ collect([$certificate->issuer, $certificate->certificate_number])->filter()->implode(' · ') ?: 'Issuer and certificate number not recorded' }}</p>
                                <div class="nxmc-chip-row is-compact">
                                    <span class="nxmc-chip {{ $status === 'verified' ? 'is-green' : ($status === 'expired' ? 'is-red' : 'is-amber') }}">{{ str($status)->replace('_', ' ')->title() }}</span>
                                    <span class="nxmc-chip">{{ $certificate->expires_at ? 'Expires '.$certificate->expires_at->format('d M Y') : 'No expiry' }}</span>
                                </div>
                            </div>
                            <div class="nxmc-evidence-card-actions">
                                @if (filled($certificate->file_path) || filled($certificate->external_url))
                                    <a class="nxmc-mini-button is-source" href="{{ route('companies.materials.certificates.source', [$company, $material->id, $certificate->id]) }}" target="_blank" rel="noopener">Source</a>
                                @endif
                                @if ($canModify && auth()->user()->hasCompanyPermission($company, 'equipment.update'))
                                    @if (! in_array($certificate->verification_status, ['verified', 'rejected'], true))
                                        <form method="POST" action="{{ route('companies.materials.certificates.verify', [$company, $material->id, $certificate->id]) }}" data-nxmc-action="certificateVerify">@csrf @method('PATCH')<input type="hidden" name="verification_status" value="verified"><button class="nxmc-mini-button is-verify" type="submit">Verify</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('companies.materials.certificates.archive', [$company, $material->id, $certificate->id]) }}" data-confirm="Archive this certificate while preserving its audit history?" data-nxmc-action="certificateArchive">@csrf @method('DELETE')<button class="nxmc-mini-button" type="submit">Archive</button></form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="nxmc-evidence-empty"><strong>No certificate evidence</strong><p>{{ $missingCertificates->isNotEmpty() ? 'Required: '.$missingCertificates->implode(', ').'.' : 'This profile has no mandatory certificates.' }}</p></div>
                    @endforelse
                </div>
            </div>

            <div>
                <div class="nxmc-evidence-list-head"><div><span class="nxmc-panel-kicker">Document register</span><h4>Supporting documents</h4></div><span>{{ number_format($activeDocuments->count()) }} active</span></div>
                <div class="nxmc-evidence-register">
                    @forelse ($activeDocuments as $document)
                        @php
                            $isExpired = $document->expires_at?->isPast() ?? false;
                            $status = $isExpired ? 'expired' : ($document->verification_status ?? 'pending');
                        @endphp
                        <article class="nxmc-evidence-card {{ $status === 'verified' ? 'is-verified' : ($status === 'expired' || $status === 'rejected' ? 'is-alert' : '') }}">
                            <div class="nxmc-evidence-card-main">
                                <span class="nxmc-evidence-type">{{ $documentTypes[$document->document_type] ?? str($document->document_type)->replace('_', ' ')->title() }}</span>
                                <strong>{{ $document->title }}</strong>
                                <p>{{ $document->original_filename ?? $document->external_url ?? 'Document source retained' }}{{ $document->revision ? ' · '.$document->revision : '' }}</p>
                                <div class="nxmc-chip-row is-compact">
                                    <span class="nxmc-chip {{ $status === 'verified' ? 'is-green' : ($status === 'expired' ? 'is-red' : 'is-amber') }}">{{ str($status)->replace('_', ' ')->title() }}</span>
                                    <span class="nxmc-chip">{{ $document->expires_at ? 'Expires '.$document->expires_at->format('d M Y') : 'No expiry' }}</span>
                                </div>
                            </div>
                            <div class="nxmc-evidence-card-actions">
                                @if (filled($document->file_path) || filled($document->external_url))
                                    <a class="nxmc-mini-button is-source" href="{{ route('companies.materials.documents.source', [$company, $material->id, $document->id]) }}" target="_blank" rel="noopener">Source</a>
                                @endif
                                @if ($canModify && auth()->user()->hasCompanyPermission($company, 'equipment.update'))
                                    @if (! in_array($document->verification_status, ['verified', 'rejected'], true))
                                        <form method="POST" action="{{ route('companies.materials.documents.verify', [$company, $material->id, $document->id]) }}" data-nxmc-action="documentVerify">@csrf @method('PATCH')<input type="hidden" name="verification_status" value="verified"><button class="nxmc-mini-button is-verify" type="submit">Verify</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('companies.materials.documents.archive', [$company, $material->id, $document->id]) }}" data-confirm="Archive this supporting document?" data-nxmc-action="documentArchive">@csrf @method('DELETE')<button class="nxmc-mini-button" type="submit">Archive</button></form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="nxmc-evidence-empty"><strong>No supporting documents</strong><p>{{ $missingDocuments->isNotEmpty() ? 'Required: '.$missingDocuments->map(fn ($type) => $documentTypes[$type] ?? $type)->implode(', ').'.' : 'No mandatory supporting document remains unresolved.' }}</p></div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>
