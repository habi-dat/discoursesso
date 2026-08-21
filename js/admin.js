/**
 * @copyright Copyright (c) 2017, florian humer <florian.humer@gmail.com>
 *
 * @author Florian Humer <florian.humer@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

(function () {
	function init() {
		const root = document.getElementById('discoursesso');
		if (!root || !window.OCP || !OCP.AppConfig) {
			return;
		}

		const msg = document.getElementById('discoursesso_settings_msg');

		function showSaved() {
			if (!msg) {
				return;
			}
			msg.style.display = '';
			window.setTimeout(function () {
				msg.style.display = 'none';
			}, 500);
		}

		function save(key, value) {
			OCP.AppConfig.setValue('discoursesso', key, value);
			showSaved();
		}

		function onChange(selector, key, checkbox) {
			const el = root.querySelector(selector);
			if (!el) {
				return;
			}
			el.addEventListener('change', function (event) {
				const target = event.target;
				const value = checkbox
					? (target.checked ? 'true' : 'false')
					: target.value;
				save(key, value);
			});
		}

		onChange('.discoursesso_clientsecret', 'clientsecret');
		onChange('.discoursesso_clienturl', 'clienturl');
		onChange('.discoursesso_replace_whitespaces', 'replace_whitespaces');
		onChange('.discoursesso_scan_for_title', 'scan_for_title');
		onChange('.discoursesso_avatar_url', 'avatar_url');
		onChange('.discoursesso_avatar_token', 'avatar_token');
		onChange('.discoursesso_force_update', 'force_update', true);
		onChange('.discoursesso_exclude_groups', 'exclude_groups', true);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
