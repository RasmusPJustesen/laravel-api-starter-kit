import assert from 'node:assert/strict'
import { randomUUID } from 'node:crypto'

const api = process.env.SMOKE_API_URL ?? 'http://127.0.0.1:8000'
const origin = process.env.FRONTEND_WEB_ORIGIN ?? 'http://127.0.0.1:3000'
const mode = process.env.STARTER_KIT_AUTH ?? 'both'
const password = 'Test-Password-123!'
const email = 'smoke-' + randomUUID() + '@example.test'

async function request(path, { method = 'GET', body, token, browser, csrf = true, expected = 200 } = {}) {
    const headers = { Accept: 'application/json' }
    if (body !== undefined) headers['Content-Type'] = 'application/json'
    if (token) {
        headers.Authorization = 'Bearer ' + token
        headers['X-Client'] = 'mobile'
    }
    if (browser) {
        headers.Origin = origin
        headers.Referer = origin + '/'
        headers['X-Client'] = 'web'
        headers.Cookie = [...browser.entries()].map(([name, value]) => name + '=' + value).join('; ')
        if (csrf && browser.has('XSRF-TOKEN')) headers['X-XSRF-TOKEN'] = decodeURIComponent(browser.get('XSRF-TOKEN'))
    }

    const response = await fetch(api + path, {
        method, headers, body: body === undefined ? undefined : JSON.stringify(body), redirect: 'manual'
    })
    if (browser) {
        for (const header of response.headers.getSetCookie()) {
            const pair = header.split(';')[0]
            const separator = pair.indexOf('=')
            const name = pair.slice(0, separator)
            const value = pair.slice(separator + 1)
            if (/max-age=0/i.test(header) || !value) browser.delete(name)
            else browser.set(name, value)
        }
    }
    const raw = await response.text()
    assert.equal(response.status, expected, method + ' ' + path + ': ' + raw.slice(0, 1000))
    return raw ? JSON.parse(raw) : null
}

const credentials = { email, password }
const registration = { name: 'Smoke Test', ...credentials, password_confirmation: password }
const first = new Map()
const second = new Map()

if (mode === 'stateful') {
    await request('/register', { method: 'POST', body: registration, expected: 404 })
    await request('/sanctum/csrf-cookie', { browser: first, expected: 204 })
    await request('/spa/register', { method: 'POST', browser: first, body: registration, expected: 201 })
    await request('/me', { browser: first })
    await request('/login', { method: 'POST', body: credentials, expected: 404 })
    console.log('Stateful-only HTTP smoke passed')
} else {
    const registered = await request('/register', { method: 'POST', body: registration, expected: 201 })
    const token = registered.meta.token
    assert.ok(token)
    await request('/me', { token })
    if (mode === 'stateless') {
        await request('/spa/login', { method: 'POST', body: credentials, expected: 404 })
        await request('/me', { expected: 401 })
        console.log('Stateless-only HTTP smoke passed')
    } else {
        await request('/spa/login', { method: 'POST', browser: first, body: credentials, csrf: false, expected: 419 })
        await request('/sanctum/csrf-cookie', { browser: first, expected: 204 })
        await request('/spa/login', { method: 'POST', browser: first, body: { ...credentials, remember: true, device_name: 'First browser' }, expected: 204 })
        await request('/me', { browser: first })
        await request('/sanctum/csrf-cookie', { browser: second, expected: 204 })
        await request('/spa/login', { method: 'POST', browser: second, body: { ...credentials, remember: true, device_name: 'Second browser' }, expected: 204 })
        const listed = await request('/user/sessions', { token })
        assert.equal(listed.data.length, 3)
        const firstId = listed.data.find(row => row.attributes.name === 'First browser').id
        await request('/user/confirm-password', { method: 'POST', token, body: { password }, expected: 204 })
        await request('/user/sessions/' + firstId, { method: 'PATCH', token, body: { name: 'Renamed browser' }, expected: 204 })
        const renamed = await request('/user/sessions', { token })
        assert.ok(renamed.data.some(row => row.attributes.name === 'Renamed browser'))
        // Simulate closing the browser: retain only persistent remember credential.
        for (const name of [...second.keys()]) if (name !== 'starter_device') second.delete(name)
        assert.ok(second.has('starter_device'))
        await request('/me', { browser: second })
        await request('/user/sessions/' + firstId, { method: 'DELETE', token, expected: 204 })
        await request('/me', { browser: first, expected: 401 })
        await request('/me', { browser: second })
        const sessions = await request('/user/sessions', { browser: second })
        const tokenId = sessions.data.find(row => row.attributes.authentication_type === 'bearer').id
        await request('/user/confirm-password', { method: 'POST', browser: second, body: { password }, expected: 204 })
        await request('/user/sessions/' + tokenId, { method: 'DELETE', browser: second, expected: 204 })
        await request('/me', { token, expected: 401 })
        await request('/logout', { method: 'POST', browser: second, expected: 204 })
        await request('/me', { browser: second, expected: 401 })
        console.log('Combined authentication, CSRF, remember-me and cross-device revocation HTTP smoke passed')
    }
}
