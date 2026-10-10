<section class="help-tools help-widget" data-help-widget aria-label="Help and accessibility tools">
    <div class="help-widget-launchers">
        <details class="accessibility-menu help-widget-item">
            <summary class="help-widget-button accessibility-menu-toggle"><i class="fas fa-universal-access" aria-hidden="true"></i><span class="help-widget-label">Accessibility options</span></summary>
            <div class="accessibility-panel help-widget-panel">
                <div class="accessibility-panel-heading help-widget-panel-header">
                    <h2 class="accessibility-panel-title">Accessibility tools</h2>
                    <button type="button" class="help-widget-text-button" data-accessibility="reset" aria-label="Reset accessibility settings">Reset</button>
                </div>
                <div class="accessibility-panel-body">
                    <fieldset class="a11y-group">
                        <legend class="a11y-group-legend">Text</legend>
                        <div class="a11y-control">
                            <label for="a11y-text-size">Text size <output data-a11y-output="text-size">100%</output></label>
                            <input id="a11y-text-size" name="a11y_text_size" type="range" min="100" max="180" step="10" value="100" data-accessibility="text-size" class="a11y-range">
                        </div>
                        <div class="a11y-control">
                            <label for="a11y-font">Font</label>
                            <select id="a11y-font" name="a11y_font" data-accessibility="font">
                                <option value="site">Site default</option>
                                <option value="readable">Clear sans-serif</option>
                                <option value="serif">Serif</option>
                            </select>
                        </div>
                        <div class="a11y-control">
                            <label for="a11y-spacing">Spacing</label>
                            <select id="a11y-spacing" name="a11y_spacing" data-accessibility="spacing">
                                <option value="normal">Standard</option>
                                <option value="relaxed">Relaxed</option>
                                <option value="wide">Wide</option>
                            </select>
                        </div>
                    </fieldset>
                    <fieldset class="a11y-group">
                        <legend class="a11y-group-legend">Colour</legend>
                        <div class="a11y-control">
                            <label for="a11y-colors">Colour display</label>
                            <select id="a11y-colors" name="a11y_colors" data-accessibility="colors">
                                <option value="normal">Standard</option>
                                <option value="contrast">High contrast</option>
                                <option value="dark">Dark</option>
                                <option value="grayscale">Greyscale</option>
                            </select>
                        </div>
                    </fieldset>
                    <fieldset class="a11y-group">
                        <legend class="a11y-group-legend">Extras</legend>
                        <div class="accessibility-actions">
                            <button type="button" class="help-widget-toggle" data-accessibility="underline" aria-pressed="false">Underline links</button>
                            <button type="button" class="help-widget-toggle" data-accessibility="motion" aria-pressed="false">Reduce motion</button>
                            <button type="button" class="help-widget-toggle" data-accessibility="read-aloud" aria-pressed="false"><i class="fas fa-volume-up" aria-hidden="true"></i> Read page aloud</button>
                        </div>
                    </fieldset>
                </div>
                <small class="help-widget-note">Saved on this device. Press Alt+0 to reset.</small>
            </div>
        </details>

        <div class="help-chat help-widget-item" data-help-chat>
            <button class="help-chat-toggle help-widget-button" type="button" aria-expanded="false" aria-controls="help-chat-panel"><i class="fas fa-comment-dots" aria-hidden="true"></i><span class="help-widget-label">Need help?</span></button>
            <div class="help-chat-panel help-widget-panel" id="help-chat-panel" role="dialog" aria-modal="false" aria-labelledby="help-chat-title" hidden>
                <div class="help-chat-heading help-widget-panel-header">
                    <div>
                        <h2 class="help-chat-title" id="help-chat-title">Lwotowone guide</h2>
                        <small>Quick answers, any time</small>
                    </div>
                    <button type="button" data-chat-close aria-label="Close help guide"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <div class="help-chat-messages" data-chat-messages role="log" aria-live="polite">
                    <p class="chat-reply">Hi there! Ask me about courses, your account, accessibility options or getting in touch.</p>
                </div>
                <form class="help-chat-form" data-chat-form>
                    <label class="sr-only" for="help-chat-input">Ask a question</label>
                    <input id="help-chat-input" name="message" type="text" maxlength="240" placeholder="Type your question" autocomplete="off" required>
                    <button type="submit" aria-label="Send question"><i class="fas fa-paper-plane" aria-hidden="true"></i></button>
                </form>
                <nav class="help-chat-links" aria-label="More help">
                    <a class="help-chat-link" href="/faq">Visit FAQs</a>
                    <a class="help-chat-link chat-human-link" href="/contact">Talk to a person</a>
                </nav>
                <p class="chat-disclaimer">Answers are automated.</p>
            </div>
        </div>
    </div>
</section>
