# JS数据结构与算法

## 一.常见数据结构

数组，栈，链表，图，散列表，队列，堆，树

### 1.数组

#### (1).push

在整个数组末尾新增一个push的数

```javascript
let arr=[1,2,3];
arr.push(4);  //结果：[1,2,3,4]
```

#### (2).pop

把末尾元素进行弹出

```javascript
let arr=[1,2,3,4];
arr.pop(); //结果：[1,2,3]
```

#### (3).shift与unshift

shift是从数组前面删除一个数，unshift是从前面插入一个数

#### (4).splice

基础语法：

```javascript
splice(n,m,k);
```

从数组的索引为n的位置删除m个数，k表示在这些删除位置增加的数

#### (5).sort

数组自带排序方法

```javascript
let arr=[1,5,3,7,4,9,2];
arr.sort((x,y)=>x-y); //大于0就交换顺序，小于0不交换顺序
console.log(arr);//结果：[1,2,3,4,5,7,9]
```

#### (6).concat

链接数组

```javascript
let arr1=[1,2,3];
let arr2=[4,5,6];
arr1.concat(arr2,7,8,9);//结果：[1,2,3,4,5,6,7,8,9]
```

#### (7).迭代方法

every,some,filter,map,foreach,reduce

##### (1.every

数组里面每一个数都符合条件才返回true

```javascript
let arr1 = [1, 2, 3]
let res = arr1.every(item => item > 0)
console.log(res) // 结果：true
```

##### (2.some

只要有一个满足条件就会返回true

##### (3.filter

把满足条件的数过滤出来

##### (4.map

映射函数，用于改数组中的内容

##### (5.foreach

遍历所有元素

##### (6.reduce

实现累加的效果

```javascript
let arr=[1,2,3,4];
let res=arr.reduce((item1,item2)=>item1+item2);//item1+item2作为item1重新进入作为回调函数的item1
console.log(res);//结果：10
```

#### (8).迭代器对象：有 Symbol.iterator 属性（能 for of）

迭代器就是一个拥有next()方法的对象，每次调用返回{value,done},value表示当前值，done表示是否遍历结束(boolean类型)

Arr,Set,Map,String,arguements,NodeList内置对象部署了symbol.iterator接口

拿迭代器：

```javascript
const arr = [1,2,3]
const it = arr[Symbol.iterator]()

console.log(it.next()) // {value:1,done:false}
console.log(it.next()) // {value:2,done:false}
console.log(it.next()) // {value:3,done:false}
console.log(it.next()) // {value:undefined,done:true}
```

#### (9).arr.from

arr.from是js内置方法，，用来把类数组对象或可迭代对象转成真正的数组

基础语法：

```javascript
Array.from(类数组/可迭代对象, 映射函数?, this指向?)
```

### 2.栈结构 (Stack)

**后进先出 (LIFO)** 的线性结构，只能在一端（栈顶）进行插入和删除。

#### 用数组模拟栈

```javascript
class Stack {
  constructor() {
    this.items = []
  }
  // push：入栈
  push(el) { this.items.push(el) }
  // pop：出栈
  pop() { return this.items.pop() }
  // peek：查看栈顶元素（不出栈）
  peek() { return this.items[this.items.length - 1] }
  // 是否为空
  isEmpty() { return this.items.length === 0 }
  // 栈大小
  size() { return this.items.length }
  // 清空
  clear() { this.items = [] }
}
```

#### 经典应用场景

| 场景 | 说明 |
|------|------|
| 函数调用栈 | JS 引擎用栈管理函数调用 |
| 括号匹配 | `{[()]}` 是否合法 |
| 撤销/重做 | Ctrl+Z / Ctrl+Y |
| 浏览器的前进后退 | 两个栈实现 |
| 十进制转二进制 | 除 2 取余，逆序输出 |

#### 括号匹配示例

```javascript
function isValid(s) {
  const stack = []
  const map = { '(': ')', '[': ']', '{': '}' }
  for (let ch of s) {
    if (map[ch]) {
      stack.push(ch)
    } else {
      if (map[stack.pop()] !== ch) return false
    }
  }
  return stack.length === 0
}
console.log(isValid('{[()]}')) // true
console.log(isValid('{[(])}')) // false
```

---

### 3.链表 (Linked List)

链表由**节点 (Node)** 组成，每个节点包含**数据域**和**指针域**，指针指向下一个节点。链表在内存中**不连续**，插入/删除 O(1)，查找 O(n)。

#### (1) 单向链表

```javascript
class ListNode {
  constructor(val, next = null) {
    this.val = val
    this.next = next
  }
}

class LinkedList {
  constructor() {
    this.head = null
    this.length = 0
  }

  // 尾部追加
  append(val) {
    const node = new ListNode(val)
    if (!this.head) {
      this.head = node
    } else {
      let cur = this.head
      while (cur.next) cur = cur.next
      cur.next = node
    }
    this.length++
  }

  // 指定位置插入 (index 从 0 开始)
  insert(index, val) {
    if (index < 0 || index > this.length) return
    const node = new ListNode(val)
    if (index === 0) {
      node.next = this.head
      this.head = node
    } else {
      let prev = this.head
      for (let i = 0; i < index - 1; i++) prev = prev.next
      node.next = prev.next
      prev.next = node
    }
    this.length++
  }

  // 删除指定位置
  removeAt(index) {
    if (index < 0 || index >= this.length) return null
    let removed
    if (index === 0) {
      removed = this.head
      this.head = this.head.next
    } else {
      let prev = this.head
      for (let i = 0; i < index - 1; i++) prev = prev.next
      removed = prev.next
      prev.next = prev.next.next
    }
    this.length--
    return removed.val
  }

  // 查找
  find(val) {
    let cur = this.head
    let idx = 0
    while (cur) {
      if (cur.val === val) return idx
      cur = cur.next
      idx++
    }
    return -1
  }

  // 转数组
  toArray() {
    const arr = []
    let cur = this.head
    while (cur) { arr.push(cur.val); cur = cur.next }
    return arr
  }
}
```

#### (2) 双向链表

每个节点有两个指针：`prev` 指向前驱，`next` 指向后继。

```javascript
class DoublyNode {
  constructor(val) {
    this.val = val
    this.prev = null
    this.next = null
  }
}
```

#### (3) 环形链表

尾节点的 `next` 指向头节点，形成闭环。常用于**约瑟夫环**问题。

#### 链表经典面试题

**反转链表（迭代法）— LeetCode 206**

```javascript
function reverseList(head) {
  let prev = null
  let cur = head
  while (cur) {
    const next = cur.next   // 暂存下一个
    cur.next = prev         // 反转指向
    prev = cur              // 前移
    cur = next              // 前移
  }
  return prev
}
```

**检测环 — LeetCode 141（快慢指针）**

```javascript
function hasCycle(head) {
  let slow = head, fast = head
  while (fast && fast.next) {
    slow = slow.next
    fast = fast.next.next
    if (slow === fast) return true
  }
  return false
}
```

**合并两个有序链表 — LeetCode 21**

```javascript
function mergeTwoLists(l1, l2) {
  const dummy = new ListNode(-1)
  let cur = dummy
  while (l1 && l2) {
    if (l1.val <= l2.val) { cur.next = l1; l1 = l1.next }
    else                  { cur.next = l2; l2 = l2.next }
    cur = cur.next
  }
  cur.next = l1 || l2
  return dummy.next
}
```

---

### 4.队列 (Queue)

**先进先出 (FIFO)** 的线性结构，在一端（队尾）入队，另一端（队头）出队。

#### 普通队列

```javascript
class Queue {
  constructor() {
    this.items = []
  }
  enqueue(el) { this.items.push(el) }      // 入队
  dequeue() { return this.items.shift() }   // 出队
  front() { return this.items[0] }          // 队头
  isEmpty() { return this.items.length === 0 }
  size() { return this.items.length }
}
```

> 注意：用数组 `shift()` 出队是 O(n)，性能敏感场景可用**对象 + head/tail 指针**实现 O(1)。

#### 双端队列 (Deque)

两端都可以入队/出队。

```javascript
class Deque {
  constructor() {
    this.items = []
  }
  addFront(el)  { this.items.unshift(el) }
  addBack(el)   { this.items.push(el) }
  removeFront() { return this.items.shift() }
  removeBack()  { return this.items.pop() }
}
```

#### 优先队列 (Priority Queue)

元素带有优先级，优先级高的先出队。通常用**堆 (Heap)** 实现（见堆章节）。

```javascript
class PriorityQueue {
  constructor() {
    this.items = []
  }
  enqueue(el, priority) {
    const node = { el, priority }
    this.items.push(node)
    this.items.sort((a, b) => a.priority - b.priority) // 简单实现，生产用堆
  }
  dequeue() { return this.items.shift() }
}
```

#### 经典应用

| 场景 | 说明 |
|------|------|
| 任务调度 | JS 事件循环 — 宏任务/微任务队列 |
| BFS | 广度优先遍历 |
| 消息队列 | RabbitMQ / Kafka |
| 滑动窗口 | 窗口最大值/最小值问题 |

---

### 5.散列表 (Hash Table / Map)

通过**哈希函数**将 key 映射为索引，实现 O(1) 平均查找/插入/删除。

#### JS 中的 Map

```javascript
const map = new Map()
map.set('name', 'zhangsan')
map.set('age', 25)
console.log(map.get('name')) // 'zhangsan'
console.log(map.has('age'))  // true
map.delete('age')
console.log(map.size)        // 1

// 遍历
for (let [k, v] of map) { console.log(k, v) }
map.forEach((v, k) => console.log(k, v))
```

#### JS 中的 Set（集合 — 不重复元素）

```javascript
const set = new Set([1, 2, 3, 3, 4])
console.log(set)              // Set {1, 2, 3, 4}
set.add(5)
set.delete(2)
console.log(set.has(3))       // true
console.log(set.size)         // 4

// 数组去重一行搞定
const unique = [...new Set([1,2,3,2,1])]  // [1,2,3]
```

#### Map vs Object

| 特性 | Map | Object |
|------|-----|--------|
| key 类型 | 任意类型（对象、函数均可） | 只能是 String/Symbol |
| 顺序 | 严格按插入顺序 | 数字 key 会排序 |
| 大小 | `map.size` | 手动 `Object.keys().length` |
| 迭代 | 直接 `for of` / `forEach` | 需 `Object.entries()` |
| 性能 | 频繁增删更快 | 适合静态结构 |

#### 手写简单哈希表

```javascript
class HashTable {
  constructor(size = 53) {
    this.keyMap = new Array(size)
  }

  _hash(key) {
    let total = 0
    const PRIME = 31
    for (let i = 0; i < Math.min(key.length, 100); i++) {
      total = (total * PRIME + key.charCodeAt(i)) % this.keyMap.length
    }
    return total
  }

  set(key, val) {
    const idx = this._hash(key)
    if (!this.keyMap[idx]) this.keyMap[idx] = []
    this.keyMap[idx].push([key, val])
  }

  get(key) {
    const idx = this._hash(key)
    const bucket = this.keyMap[idx]
    if (bucket) {
      for (let [k, v] of bucket) if (k === key) return v
    }
    return undefined
  }
}
```

#### 哈希冲突解决方案

- **链地址法 (Separate Chaining)**：同一索引存链表/数组（上面手写版用的就是这种）
- **开放寻址法 (Open Addressing)**：冲突后找下一个空位（线性探测/平方探测）

---

### 6.堆 (Heap)

堆是一种**完全二叉树**，分为：

- **最小堆**：每个节点的值 ≤ 子节点的值 → 根节点最小
- **最大堆**：每个节点的值 ≥ 子节点的值 → 根节点最大

> JS 中没有内置堆，通常用数组模拟，LeetCode 手写或直接复制。

#### 最小堆实现

```javascript
class MinHeap {
  constructor() {
    this.heap = []
  }

  // 获取父/左/右节点索引
  _parent(i)  { return (i - 1) >> 1 }
  _left(i)    { return i * 2 + 1 }
  _right(i)   { return i * 2 + 2 }

  // 上浮 (insert 用)
  _swim(i) {
    while (i > 0) {
      const p = this._parent(i)
      if (this.heap[p] <= this.heap[i]) break
      ;[this.heap[p], this.heap[i]] = [this.heap[i], this.heap[p]]
      i = p
    }
  }

  // 下沉 (remove 用)
  _sink(i) {
    while (true) {
      let min = i
      const l = this._left(i), r = this._right(i)
      if (l < this.heap.length && this.heap[l] < this.heap[min]) min = l
      if (r < this.heap.length && this.heap[r] < this.heap[min]) min = r
      if (min === i) break
      ;[this.heap[i], this.heap[min]] = [this.heap[min], this.heap[i]]
      i = min
    }
  }

  // 插入
  insert(val) {
    this.heap.push(val)
    this._swim(this.heap.length - 1)
  }

  // 弹出堆顶（最小值）
  remove() {
    if (this.heap.length === 0) return null
    const top = this.heap[0]
    const last = this.heap.pop()
    if (this.heap.length > 0) {
      this.heap[0] = last
      this._sink(0)
    }
    return top
  }

  peek() { return this.heap[0] }
  size() { return this.heap.length }
}
```

#### 使用场景

| 场景 | 用什么堆 |
|------|----------|
| 优先队列 | 最小堆 / 最大堆 |
| Top K 问题 | 最小堆（找最大的 K 个） |
| 堆排序 | 最大堆 O(n log n) |
| 中位数维护 | 两个堆（大顶堆 + 小顶堆） |

---

### 7.树 (Tree)

树是**n 个结点的有限集合**，有且仅有一个根节点，其余结点分为互不相交的子树。

#### 二叉树 (Binary Tree)

每个节点最多有两个子节点。

```javascript
class TreeNode {
  constructor(val, left = null, right = null) {
    this.val = val
    this.left = left
    this.right = right
  }
}
```

#### 二叉树的遍历

```
        1
      /   \
     2     3
    / \   / \
   4   5 6   7
```

**(1) 前序遍历 (根→左→右)：1 2 4 5 3 6 7**

```javascript
// 递归
function preorder(root) {
  if (!root) return
  console.log(root.val)
  preorder(root.left)
  preorder(root.right)
}

// 迭代（栈）
function preorderIter(root) {
  if (!root) return
  const stack = [root]
  while (stack.length) {
    const node = stack.pop()
    console.log(node.val)
    if (node.right) stack.push(node.right) // 先压右
    if (node.left)  stack.push(node.left)  // 后压左 → 左先出
  }
}
```

**(2) 中序遍历 (左→根→右)：4 2 5 1 6 3 7**

```javascript
// BST 中序遍历结果有序（升序）
function inorder(root) {
  if (!root) return
  inorder(root.left)
  console.log(root.val)
  inorder(root.right)
}

// 迭代
function inorderIter(root) {
  const stack = []
  let cur = root
  while (cur || stack.length) {
    while (cur) { stack.push(cur); cur = cur.left }
    cur = stack.pop()
    console.log(cur.val)
    cur = cur.right
  }
}
```

**(3) 后序遍历 (左→右→根)：4 5 2 6 7 3 1**

```javascript
function postorder(root) {
  if (!root) return
  postorder(root.left)
  postorder(root.right)
  console.log(root.val)
}
```

**(4) 层序遍历 (BFS)：1 2 3 4 5 6 7**

```javascript
function levelOrder(root) {
  if (!root) return
  const queue = [root]
  while (queue.length) {
    const node = queue.shift()
    console.log(node.val)
    if (node.left)  queue.push(node.left)
    if (node.right) queue.push(node.right)
  }
}
```

#### 二叉搜索树 (BST)

- 左子树所有节点值 < 根节点值
- 右子树所有节点值 > 根节点值
- 左右子树也都是 BST
- **中序遍历是升序序列**

```javascript
class BST {
  constructor() {
    this.root = null
  }

  insert(val) {
    const node = new TreeNode(val)
    if (!this.root) { this.root = node; return }
    let cur = this.root
    while (true) {
      if (val < cur.val) {
        if (!cur.left) { cur.left = node; return }
        cur = cur.left
      } else {
        if (!cur.right) { cur.right = node; return }
        cur = cur.right
      }
    }
  }

  search(val) {
    let cur = this.root
    while (cur) {
      if (val === cur.val) return cur
      cur = val < cur.val ? cur.left : cur.right
    }
    return null
  }

  // 删除节点（三种情况：叶子 / 有一个子节点 / 有两个子节点）
  _remove(node, val) {
    if (!node) return null
    if (val < node.val) {
      node.left = this._remove(node.left, val)
    } else if (val > node.val) {
      node.right = this._remove(node.right, val)
    } else {
      // 情况1：叶子节点 / 只有一个子节点
      if (!node.left) return node.right
      if (!node.right) return node.left
      // 情况2：有两个子节点 → 找右子树最小节点替代
      let minNode = node.right
      while (minNode.left) minNode = minNode.left
      node.val = minNode.val
      node.right = this._remove(node.right, minNode.val)
    }
    return node
  }

  remove(val) {
    this.root = this._remove(this.root, val)
  }
}
```

#### 平衡二叉树 (AVL) 与 红黑树

- **AVL**：任意节点左右子树高度差 ≤ 1，通过旋转维持平衡。查找快，插入删除需频繁旋转。
- **红黑树**：通过颜色（红/黑）+ 规则维持近似平衡。插入删除代价更低，是 TreeMap / TreeSet 的底层实现。JS 中 `Map`/`Set` 的 V8 实现（元素较多时）也用到。

---

### 8.图 (Graph)

图由**顶点 (Vertex)** 和**边 (Edge)** 组成。

#### 图的存储方式

**(1) 邻接矩阵 — 二维数组**  
`graph[i][j] = 1` 表示顶点 i→j 有边。适合稠密图，O(n²) 空间。

**(2) 邻接表 — 数组 + 链表 / Map**  
每个顶点存一个数组表示邻居。适合稀疏图，O(V+E) 空间。

```javascript
// 邻接表 — JS 最常用
const graph = {
  A: ['B', 'C'],
  B: ['A', 'D', 'E'],
  C: ['A', 'F'],
  D: ['B'],
  E: ['B', 'F'],
  F: ['C', 'E'],
}
```

#### 图的遍历

**(1) 深度优先搜索 DFS — 栈 / 递归**

```javascript
function dfs(graph, start, visited = new Set()) {
  console.log(start)
  visited.add(start)
  for (let neighbor of graph[start]) {
    if (!visited.has(neighbor)) dfs(graph, neighbor, visited)
  }
}
```

**(2) 广度优先搜索 BFS — 队列**

```javascript
function bfs(graph, start) {
  const visited = new Set([start])
  const queue = [start]
  while (queue.length) {
    const node = queue.shift()
    console.log(node)
    for (let neighbor of graph[node]) {
      if (!visited.has(neighbor)) {
        visited.add(neighbor)
        queue.push(neighbor)
      }
    }
  }
}
```

#### 最短路径 — Dijkstra 算法

```javascript
function dijkstra(graph, start) {
  const dist = {}  // 到各点的最短距离
  const prev = {}  // 前驱节点，用于回溯路径
  const visited = new Set()

  for (let v in graph) dist[v] = Infinity
  dist[start] = 0

  while (visited.size < Object.keys(graph).length) {
    // 选未访问中距离最小的
    let u = null
    for (let v in graph) {
      if (!visited.has(v) && (u === null || dist[v] < dist[u])) u = v
    }
    visited.add(u)
    // 松弛操作
    for (let v in graph[u]) {
      const d = dist[u] + graph[u][v]
      if (d < dist[v]) { dist[v] = d; prev[v] = u }
    }
  }
  return { dist, prev }
}

// 示例
const g = {
  A: { B: 4, C: 2 },
  B: { A: 4, C: 1, D: 5 },
  C: { A: 2, B: 1, D: 8, E: 10 },
  D: { B: 5, C: 8, E: 2 },
  E: { C: 10, D: 2 },
}
console.log(dijkstra(g, 'A').dist)
// { A: 0, B: 3, C: 2, D: 7, E: 9 }
```

---

## 二.常见算法

### 1.排序算法

#### (1) 冒泡排序 — O(n²)

```javascript
function bubbleSort(arr) {
  for (let i = 0; i < arr.length - 1; i++) {
    let swapped = false
    for (let j = 0; j < arr.length - 1 - i; j++) {
      if (arr[j] > arr[j + 1]) {
        ;[arr[j], arr[j + 1]] = [arr[j + 1], arr[j]]
        swapped = true
      }
    }
    if (!swapped) break // 已有序，提前结束
  }
  return arr
}
```

#### (2) 选择排序 — O(n²) 不稳定

```javascript
function selectionSort(arr) {
  for (let i = 0; i < arr.length - 1; i++) {
    let minIdx = i
    for (let j = i + 1; j < arr.length; j++) {
      if (arr[j] < arr[minIdx]) minIdx = j
    }
    ;[arr[i], arr[minIdx]] = [arr[minIdx], arr[i]]
  }
  return arr
}
```

#### (3) 插入排序 — O(n²)，近乎有序时 O(n)

```javascript
function insertionSort(arr) {
  for (let i = 1; i < arr.length; i++) {
    const key = arr[i]
    let j = i - 1
    while (j >= 0 && arr[j] > key) {
      arr[j + 1] = arr[j]
      j--
    }
    arr[j + 1] = key
  }
  return arr
}
```

#### (4) 快速排序 — O(n log n)，最坏 O(n²)

```javascript
function quickSort(arr) {
  if (arr.length <= 1) return arr
  const pivot = arr[arr.length - 1]
  const left = []
  const right = []
  for (let i = 0; i < arr.length - 1; i++) {
    if (arr[i] < pivot) left.push(arr[i])
    else right.push(arr[i])
  }
  return [...quickSort(left), pivot, ...quickSort(right)]
}

// 原地快排（节省空间）
function quickSortInPlace(arr, lo = 0, hi = arr.length - 1) {
  if (lo >= hi) return
  const pivot = arr[hi]
  let i = lo
  for (let j = lo; j < hi; j++) {
    if (arr[j] < pivot) {
      ;[arr[i], arr[j]] = [arr[j], arr[i]]
      i++
    }
  }
  ;[arr[i], arr[hi]] = [arr[hi], arr[i]]
  quickSortInPlace(arr, lo, i - 1)
  quickSortInPlace(arr, i + 1, hi)
}
```

#### (5) 归并排序 — O(n log n) 稳定

```javascript
function mergeSort(arr) {
  if (arr.length <= 1) return arr
  const mid = arr.length >> 1
  const left = mergeSort(arr.slice(0, mid))
  const right = mergeSort(arr.slice(mid))
  return merge(left, right)
}

function merge(left, right) {
  const res = []
  let i = 0, j = 0
  while (i < left.length && j < right.length) {
    res.push(left[i] <= right[j] ? left[i++] : right[j++])
  }
  return [...res, ...left.slice(i), ...right.slice(j)]
}
```

#### 排序对比

| 算法 | 平均 | 最坏 | 空间 | 稳定 |
|------|------|------|------|------|
| 冒泡 | O(n²) | O(n²) | O(1) | ✅ |
| 选择 | O(n²) | O(n²) | O(1) | ❌ |
| 插入 | O(n²) | O(n²) | O(1) | ✅ |
| 快速 | O(n log n) | O(n²) | O(log n) | ❌ |
| 归并 | O(n log n) | O(n log n) | O(n) | ✅ |
| 堆排 | O(n log n) | O(n log n) | O(1) | ❌ |
| 计数 | O(n+k) | O(n+k) | O(k) | ✅ |

---

### 2.搜索算法

#### (1) 二分查找 — O(log n)（要求有序数组）

```javascript
function binarySearch(arr, target) {
  let lo = 0, hi = arr.length - 1
  while (lo <= hi) {
    const mid = (lo + hi) >> 1
    if (arr[mid] === target) return mid
    if (arr[mid] < target) lo = mid + 1
    else hi = mid - 1
  }
  return -1
}
```

#### (2) 二分查找变体

```javascript
// 查找第一个等于 target 的位置
function lowerBound(arr, target) {
  let lo = 0, hi = arr.length
  while (lo < hi) {
    const mid = (lo + hi) >> 1
    if (arr[mid] < target) lo = mid + 1
    else hi = mid
  }
  return lo
}

// 查找第一个大于 target 的位置
function upperBound(arr, target) {
  let lo = 0, hi = arr.length
  while (lo < hi) {
    const mid = (lo + hi) >> 1
    if (arr[mid] <= target) lo = mid + 1
    else hi = mid
  }
  return lo
}
```

---

### 3.递归 (Recursion)

```javascript
// 阶乘
function factorial(n) {
  if (n <= 1) return 1          // 终止条件
  return n * factorial(n - 1)   // 递归体
}

// 斐波那契（带记忆化）
function fib(n, memo = {}) {
  if (n <= 1) return n
  if (memo[n]) return memo[n]
  memo[n] = fib(n - 1, memo) + fib(n - 2, memo)
  return memo[n]
}
console.log(fib(50)) // 12586269025（无记忆化会卡死）
```

---

### 4.动态规划 (Dynamic Programming)

核心思想：**拆分子问题 + 记忆化 / 填表**，避免重复计算。

#### 经典题：爬楼梯 (LeetCode 70)

每次爬 1 或 2 阶，爬上 n 阶有多少种方法？

```javascript
// dp[i] = dp[i-1] + dp[i-2]
function climbStairs(n) {
  if (n <= 2) return n
  let prev2 = 1, prev1 = 2
  for (let i = 3; i <= n; i++) {
    ;[prev2, prev1] = [prev1, prev1 + prev2]
  }
  return prev1
}
```

#### 0-1 背包问题

```javascript
function knapsack(weights, values, capacity) {
  const n = weights.length
  // dp[i][w] = 前 i 个物品，容量 w 的最大价值
  const dp = Array.from({ length: n + 1 }, () => Array(capacity + 1).fill(0))

  for (let i = 1; i <= n; i++) {
    for (let w = 1; w <= capacity; w++) {
      if (weights[i - 1] > w) {
        dp[i][w] = dp[i - 1][w]   // 装不下
      } else {
        dp[i][w] = Math.max(
          dp[i - 1][w],                           // 不装
          dp[i - 1][w - weights[i - 1]] + values[i - 1] // 装
        )
      }
    }
  }
  return dp[n][capacity]
}
```

#### 最长公共子序列 (LCS)

```javascript
function lcs(text1, text2) {
  const m = text1.length, n = text2.length
  const dp = Array.from({ length: m + 1 }, () => Array(n + 1).fill(0))
  for (let i = 1; i <= m; i++) {
    for (let j = 1; j <= n; j++) {
      if (text1[i - 1] === text2[j - 1]) {
        dp[i][j] = dp[i - 1][j - 1] + 1
      } else {
        dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1])
      }
    }
  }
  return dp[m][n]
}
```

---

### 5.贪心算法 (Greedy)

每步都选**当前最优**，不保证全局最优。

**找零钱问题**（硬币面额规范时适用）：

```javascript
function coinChange(coins, amount) {
  coins.sort((a, b) => b - a) // 从大到小
  let count = 0
  for (let coin of coins) {
    while (amount >= coin) {
      amount -= coin
      count++
    }
  }
  return amount === 0 ? count : -1
}
```

---

### 6.回溯算法 (Backtracking)

尝试所有可能，不满足条件时**撤销选择**（"试错 — 撤销"）。

#### 全排列 (LeetCode 46)

```javascript
function permute(nums) {
  const res = []
  const used = new Array(nums.length).fill(false)

  function backtrack(path) {
    if (path.length === nums.length) {
      res.push([...path])
      return
    }
    for (let i = 0; i < nums.length; i++) {
      if (used[i]) continue
      path.push(nums[i])
      used[i] = true
      backtrack(path)
      path.pop()       // 撤销
      used[i] = false  // 撤销
    }
  }

  backtrack([])
  return res
}

console.log(permute([1, 2, 3]))
// [[1,2,3],[1,3,2],[2,1,3],[2,3,1],[3,1,2],[3,2,1]]
```

#### N 皇后 (LeetCode 51)

```javascript
function solveNQueens(n) {
  const res = []
  const board = Array.from({ length: n }, () => '.'.repeat(n))

  function backtrack(row, cols, diag1, diag2) {
    if (row === n) { res.push([...board]); return }
    for (let col = 0; col < n; col++) {
      const d1 = row - col, d2 = row + col
      if (cols.has(col) || diag1.has(d1) || diag2.has(d2)) continue
      cols.add(col); diag1.add(d1); diag2.add(d2)
      board[row] = '.'.repeat(col) + 'Q' + '.'.repeat(n - col - 1)
      backtrack(row + 1, cols, diag1, diag2)
      cols.delete(col); diag1.delete(d1); diag2.delete(d2)
    }
  }

  backtrack(0, new Set(), new Set(), new Set())
  return res
}
```

---

### 7.滑动窗口 (Sliding Window)

维护一个窗口，不断滑动求解。常用于子串/子数组问题。

```javascript
// 无重复字符的最长子串 (LeetCode 3)
function lengthOfLongestSubstring(s) {
  const map = new Map()
  let left = 0, max = 0
  for (let right = 0; right < s.length; right++) {
    if (map.has(s[right])) {
      left = Math.max(left, map.get(s[right]) + 1)
    }
    map.set(s[right], right)
    max = Math.max(max, right - left + 1)
  }
  return max
}
```

---

### 8.双指针 (Two Pointers)

```javascript
// 盛最多水的容器 (LeetCode 11)
function maxArea(height) {
  let l = 0, r = height.length - 1, max = 0
  while (l < r) {
    max = Math.max(max, Math.min(height[l], height[r]) * (r - l))
    if (height[l] < height[r]) l++
    else r--
  }
  return max
}

// 三数之和 (LeetCode 15)
function threeSum(nums) {
  nums.sort((a, b) => a - b)
  const res = []
  for (let i = 0; i < nums.length - 2; i++) {
    if (i > 0 && nums[i] === nums[i - 1]) continue // 去重
    let l = i + 1, r = nums.length - 1
    while (l < r) {
      const sum = nums[i] + nums[l] + nums[r]
      if (sum === 0) {
        res.push([nums[i], nums[l], nums[r]])
        while (l < r && nums[l] === nums[l + 1]) l++ // 去重
        while (l < r && nums[r] === nums[r - 1]) r-- // 去重
        l++; r--
      } else if (sum < 0) {
        l++
      } else {
        r--
      }
    }
  }
  return res
}
```

---

## 三.复杂度速查

| 操作 | 数组 | 链表 | 哈希表 | BST (平均) | BST (最坏) |
|------|------|------|--------|-----------|-----------|
| 访问 | O(1) | O(n) | O(1) | O(log n) | O(n) |
| 查找 | O(n) | O(n) | O(1) | O(log n) | O(n) |
| 插入 | O(n) | O(1) | O(1) | O(log n) | O(n) |
| 删除 | O(n) | O(1) | O(1) | O(log n) | O(n) |

| 图算法 | 时间复杂度 |
|--------|-----------|
| BFS / DFS | O(V + E) |
| Dijkstra | O((V+E) log V) 堆优化 |
| 拓扑排序 | O(V + E) |

---

## 四.LeetCode 刷题路线建议

1. **入门 (Easy)**：两数之和、有效括号、合并两个有序链表、爬楼梯、二叉树最大深度
2. **进阶 (Medium)**：无重复字符最长子串、三数之和、二叉树的层序遍历、岛屿数量、全排列
3. **提高 (Hard)**：编辑距离、最小覆盖子串、接雨水、LRU 缓存、N 皇后

> 核心心法：**先想清楚数据结构和算法选型 → 画图模拟过程 → 写代码 → 测试边界条件**