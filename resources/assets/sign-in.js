// these JS + SCSS will be automatically available after installing the package
import { registerNajaExtensions } from './core/base.js';
import Spinner from './naja/spinner.js';
import HyperlinkDisable from './naja/hyperlink-disable.js';

// drago-ex extensions
import { SubmitButtonDisable } from 'drago-form';
import { BootstrapDropdowns } from 'drago-component';
import { ToastHandler } from 'drago-application';

// compostrap: the theme switch, the button that shows a password, the meter of the strength of a new password,
// the slots of the recovery code and Esc for the CSS tooltips
import { ThemeSwitcher } from 'compostrap/theme-switcher';
import { PasswordToggle } from 'compostrap/password-toggle';
import { Strength } from 'compostrap/strength';
import { OtpInput } from 'compostrap/otp-input';
import { CssTooltip } from 'compostrap/tooltip';

// page styles
import './sign-in.scss';

new ThemeSwitcher({ lightIcon: 'bi bi-sun', darkIcon: 'bi bi-moon' }).initialize();
PasswordToggle.init();
CssTooltip.init();

// the translated names of the levels and the least length of a password are printed on the body by the layout
const { strengthMinLength, strengthWeak, strengthFair, strengthGood, strengthStrong } = document.body.dataset;
Strength.init({
	minLength: Number(strengthMinLength) || 8,
	messages: { weak: strengthWeak, fair: strengthFair, good: strengthGood, strong: strengthStrong },
});

// the slots of a code are drawn at once, also in a redrawn snippet (the steps of the recovery)
OtpInput.init();
naja.snippetHandler.addEventListener('afterUpdate', (event) => OtpInput.update(event.detail.snippet));

registerNajaExtensions(
	Spinner,
	HyperlinkDisable,
	SubmitButtonDisable,
	BootstrapDropdowns,
	ToastHandler
);
