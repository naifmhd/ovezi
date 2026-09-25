Source: https://registry.npmjs.org/decode-uri-component/-/decode-uri-component-0.5.0.tgz

Upstream 0.5.0 resolves GHSA-vcc3-ghjq-m6fr. The only JavaScript change is replacing its ESM default export with module.exports so query-string 7 remains compatible. No decoding logic changed. Remove this shim when Expo Router supports the patched ESM dependency directly.

Upstream archive SHA-512 (base64): 1BiQVoK8C9gUbQU6NzAtO/tkz2qOFpEObMWpcFvhx4fYnj4Oc5yzaJN/LD36ihkVUdXyh5ZekzX+yM+ty/SrPg==
