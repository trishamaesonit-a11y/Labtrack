(function () {
  var button = document.getElementById('googleLogin');
  var message = document.getElementById('googleLoginMessage');
  var config = window.LABTRACK_FIREBASE_CONFIG || {};
  if (!button) return;

  function showMessage(text, isError) {
    message.textContent = text;
    message.classList.toggle('error', !!isError);
  }

  if (!config.apiKey || !config.authDomain || !config.projectId || !config.appId) {
    button.disabled = true;
    showMessage('Google sign-in needs Firebase configuration. See README.md setup instructions.', true);
    return;
  }

  if (!window.firebase || !firebase.auth) {
    button.disabled = true;
    showMessage('Firebase could not load. Check your internet connection and try again.', true);
    return;
  }

  try {
    if (!firebase.apps.length) firebase.initializeApp(config);
  } catch (error) {
    button.disabled = true;
    showMessage('Firebase configuration is invalid. Check the Firebase web app settings.', true);
    return;
  }

  if (config.measurementId && firebase.analytics && firebase.analytics.isSupported) {
    firebase.analytics.isSupported().then(function (supported) {
      if (supported) firebase.analytics();
    }).catch(function (error) {
      console.warn('Firebase Analytics could not initialize:', error);
    });
  }

  var auth = firebase.auth();
  var provider = new firebase.auth.GoogleAuthProvider();

  button.addEventListener('click', function () {
    button.disabled = true;
    showMessage('Opening Google sign-in…', false);
    auth.signInWithPopup(provider)
      .then(function (result) { return result.user.getIdToken(); })
      .then(function (idToken) {
        var csrf = document.querySelector('#loginForm input[name="_csrf"]').value;
        return fetch('firebase_login.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
          body: new URLSearchParams({ _csrf: csrf, id_token: idToken }).toString()
        });
      })
      .then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok) throw new Error(data.error || 'Firebase sign-in failed.');
          if (!data.redirect) throw new Error('The server returned an invalid sign-in response.');
          window.location.assign(data.redirect);
        });
      })
      .catch(function (error) {
        var text = error && error.code === 'auth/popup-closed-by-user'
          ? 'Google sign-in was cancelled.'
          : (error && error.message ? error.message : 'Google sign-in failed. Try again.');
        showMessage(text, true);
        button.disabled = false;
      });
  });
}());
