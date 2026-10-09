<section class="help-tools" aria-label="Help and accessibility tools">
    <details class="accessibility-menu">
        <summary aria-label="Accessibility options"><i class="fas fa-universal-access" aria-hidden="true"></i><span>Accessibility</span></summary>
        <div class="accessibility-panel">
            <div class="accessibility-panel-heading"><strong>Accessibility tools</strong><button type="button" data-accessibility="reset">Reset all</button></div>
            <div class="a11y-control"><label for="a11y-text-size">Text size <output data-a11y-output="text-size">100%</output></label><input id="a11y-text-size" name="a11y_text_size" type="range" min="100" max="180" step="10" value="100" data-accessibility="text-size"></div>
            <div class="a11y-control"><label for="a11y-font">Reading font</label><select id="a11y-font" name="a11y_font" data-accessibility="font"><option value="site">Site default</option><option value="readable">Clear sans-serif</option><option value="serif">Serif</option></select></div>
            <div class="a11y-control"><label for="a11y-spacing">Text spacing</label><select id="a11y-spacing" name="a11y_spacing" data-accessibility="spacing"><option value="normal">Standard</option><option value="relaxed">Relaxed</option><option value="wide">Wide</option></select></div>
            <div class="a11y-control"><label for="a11y-colors">Colour display</label><select id="a11y-colors" name="a11y_colors" data-accessibility="colors"><option value="normal">Standard</option><option value="contrast">High contrast</option><option value="dark">Dark display</option><option value="grayscale">Grayscale</option></select></div>
            <div class="accessibility-actions"><button type="button" data-accessibility="underline" aria-pressed="false">Underline links</button><button type="button" data-accessibility="motion" aria-pressed="false">Reduce motion</button><button type="button" data-accessibility="read-aloud" aria-pressed="false"><i class="fas fa-volume-up" aria-hidden="true"></i> Read page aloud</button></div>
            <small>Preferences are saved on this device. Keyboard: press Alt+0 to reset.</small>
        </div>
    </details>
    <div class="help-chat" data-help-chat>
        <button class="help-chat-toggle" type="button" aria-expanded="false" aria-controls="help-chat-panel"><i class="fas fa-comment-dots" aria-hidden="true"></i><span>Need help?</span></button>
        <div class="help-chat-panel" id="help-chat-panel" hidden>
            <div class="help-chat-heading"><div><strong>Lwotowone guide</strong><small>Quick answers to help you find your way</small></div><button type="button" data-chat-close aria-label="Close help assistant">×</button></div>
            <div class="help-chat-messages" data-chat-messages aria-live="polite"><p class="chat-reply">Hello! I can point you to courses, account help, accessibility options and our team. What would you like help with?</p></div>
            <form data-chat-form><label class="sr-only" for="help-chat-input">Ask a question</label><input id="help-chat-input" name="message" maxlength="240" placeholder="Type a question…" required><button type="submit" aria-label="Send question"><i class="fas fa-paper-plane" aria-hidden="true"></i></button></form>
            <a class="chat-human-link" href="/faq">Visit FAQs</a><span class="chat-disclaimer">Automated guidance · <a href="/contact">Contact a person</a></span>
        </div>
    </div>
</section>
