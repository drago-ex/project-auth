import "./base.js"; // Located in Drago Project
import { PasswordToggle, SubmitButtonDisable } from "drago-form";
import "./sign.in.scss";

/* naja extensions */
function registerExtensions(...extensions) {
	extensions.forEach(ext => naja.registerExtension(new ext()));
}

registerExtensions(PasswordToggle, SubmitButtonDisable);
