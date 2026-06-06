// these JS + SCSS will be automatically available after installing the package
import { registerNajaExtensions } from "./core/base.js";
import Spinner from "./naja/spinner.js";
import HyperlinkDisable from "./naja/hyperlink-disable.js";

// drago-form extensions
import { PasswordToggle, SubmitButtonDisable } from "drago-form";
import { ToastHandler } from "drago-application";

// page styles
import "./sign-in.scss";

initAuthTheme();

// registration naja extensions
registerNajaExtensions(
	Spinner,
	HyperlinkDisable,
	PasswordToggle,
	SubmitButtonDisable,
	ToastHandler
);

function initAuthTheme() {
	const themeTarget = document.documentElement;
	const toggle = document.getElementById("theme-toggle");
	const storageKey = "project-auth-theme";
	const themeAttribute = "data-bs-theme";
	const currentTheme = localStorage.getItem(storageKey)
		|| themeTarget.getAttribute(themeAttribute)
		|| "light";

	applyTheme(themeTarget, currentTheme);
	renderThemeToggle(toggle, currentTheme);

	toggle?.addEventListener("click", (event) => {
		event.preventDefault();

		const nextTheme = themeTarget.getAttribute(themeAttribute) === "light"
			? "dark"
			: "light";

		applyTheme(themeTarget, nextTheme);
		localStorage.setItem(storageKey, nextTheme);
		renderThemeToggle(toggle, nextTheme);
	});
}

function applyTheme(themeTarget, theme) {
	themeTarget.setAttribute("data-bs-theme", theme);
}

function renderThemeToggle(toggle, theme) {
	if (!toggle) {
		return;
	}

	toggle.textContent = theme === "dark"
		? "Switch to light"
		: "Switch to dark";
}
