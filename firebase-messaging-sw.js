// web/firebase-messaging-sw.js
// This file MUST exist in your web/ folder for Firebase Messaging to work on Flutter Web.
// Flutter serves it automatically from the web/ directory.

importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js');

// ── Your Firebase project config ────────────────────────────
// These values match your Firebase project (datingapp-ce711).
// If you change your Firebase project, update these too.
firebase.initializeApp({
  apiKey:            "AIzaSyAZTOp64f6GSNJsUvQ2mo5bBpBx0aO8uXc",
  authDomain:        "datingapp-ce711.firebaseapp.com",
  projectId:         "datingapp-ce711",
  storageBucket:     "datingapp-ce711.firebasestorage.app",
  messagingSenderId: "138536529379",
  appId:             "1:138536529379:web:dummy_id",   // ← Replace dummy_id with your real Web App ID
  //                                                       from Firebase Console → Project Settings → Your Apps
});

const messaging = firebase.messaging();

// Handle background push notifications (when app tab is not active)
messaging.onBackgroundMessage((payload) => {
  console.log('[SW] Background message received:', payload);

  const { title, body } = payload.notification || {};
  const notificationOptions = {
    body:  body  || '',
    icon:  '/icons/Icon-192.png',
    badge: '/icons/Icon-192.png',
    data:  payload.data || {},
  };

  self.registration.showNotification(title || 'LegitDate', notificationOptions);
});

// Handle notification click — open the app
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      if (clientList.length > 0) {
        return clientList[0].focus();
      }
      return clients.openWindow('/');
    })
  );
});
