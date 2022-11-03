export default function About({ data }) {
  console.log(data);

  return <div>About</div>
}

export async function getServerSideProps(context) {
  // Fetch data from external API
  const res = await fetch('http://localhost:9090/graphql', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      query: `
          query {
              getBooks {
                  id
                  title
                  author {
                      id
                      name
                  }
              }
          }
      `,
    }),
  })
  const data = await res.json()

  // Pass data to the page via props
  return { props: { data } }
}