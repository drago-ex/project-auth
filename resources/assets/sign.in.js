// these JS + SCSS will be automatically available after installing the package
import "./core/base.js";
import "./naja/naja.spinner.js";
import "./naja/naja.spinner.scss";

// drago-form extensions
import { PasswordToggle, SubmitButtonDisable } from "drago-form";

// page styles
import "./sign.in.scss";

// registration naja extensions
function registerExtensions(...extensions) {
	extensions.forEach(Extension => {
		naja.registerExtension(new Extension());
	});
}

registerExtensions(
	PasswordToggle,
	SubmitButtonDisable
);
